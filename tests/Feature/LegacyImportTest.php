<?php

namespace Tests\Feature;

use App\Enerjisa\Models\Account;
use App\Services\LegacyImporter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    private Encrypter $legacy;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.legacy' => array_replace(config('database.connections.pgsql'), ['database' => 'reaktifradar_import_test'])]);
        DB::purge('legacy');
        Artisan::call('migrate:fresh', ['--database' => 'legacy', '--force' => true]);
        $key = random_bytes(32);
        config(['legacy.key' => 'base64:'.base64_encode($key), 'legacy.previous_keys' => []]);
        $this->legacy = new Encrypter($key, 'AES-256-CBC');
        $db = DB::connection('legacy');
        $db->table('users')->insert([
            ['id' => 8, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => bcrypt('Test123'), 'role' => 'super_admin'],
            ['id' => 9, 'name' => 'Support', 'email' => 'support@example.test', 'password' => bcrypt('Test123'), 'role' => 'support'],
        ]);
        $db->table('enerjisa_accounts')->insert(['id' => 10, 'name' => 'Firma', 'created_at' => '2026-09-21 10:00:00+03', 'client_secret' => $this->legacy->encryptString('source-secret')]);
        $db->table('enerjisa_users')->insert(['id' => 11, 'account_id' => 10, 'name' => 'User', 'email' => 'user@example.test', 'password' => bcrypt('Test123')]);
        $db->table('enerjisa_queries')->insert(['id' => 12, 'account_id' => 10, 'kind' => '1', 'parameters' => '{}', 'payload' => $this->legacy->encryptString('{"values":[]}')]);
        $db->table('enerjisa_notification_rules')->insert(['id' => 13, 'account_id' => 10, 'installation' => '123']);
        $db->table('enerjisa_notification_deliveries')->insert(['id' => 14, 'rule_id' => 13, 'scheduled_date' => '2026-09-21', 'channel' => 'email', 'status' => 'sent', 'attempts' => 1, 'body' => $this->legacy->encryptString('Gönderildi')]);
        $db->table('enerjisa_system_settings')->insert(['key' => 'telegram', 'updated_by' => 9, 'value' => $this->legacy->encryptString('{"token":"secret"}')]);
    }

    public function test_dry_run_does_not_write_and_apply_preserves_records_with_new_encryption(): void
    {
        $importer = app(LegacyImporter::class);
        $counts = $importer->run();
        $this->assertSame(1, $counts['users']);
        $this->assertSame(0, Account::count());
        $this->assertSame($counts, $importer->run(false));
        $this->assertSame('source-secret', Account::findOrFail(10)->client_secret);
        $this->assertNotSame(DB::connection('legacy')->table('enerjisa_accounts')->value('client_secret'), DB::table('enerjisa_accounts')->value('client_secret'));
        $this->assertDatabaseHas('enerjisa_notification_deliveries', ['id' => 14, 'status' => 'sent', 'attempts' => 1]);
        $this->assertDatabaseHas('enerjisa_system_settings', ['key' => 'telegram', 'updated_by' => null]);
        $this->assertSame(2, DB::connection('legacy')->table('users')->count());
        $this->assertGreaterThan(10, Account::create(['name' => 'Yeni'])->id);
        $this->expectException(RuntimeException::class);
        $importer->run(false);
    }

    public function test_corrupt_encrypted_payload_rolls_back_all_target_records(): void
    {
        DB::connection('legacy')->table('enerjisa_queries')->update(['payload' => 'invalid']);
        try {
            app(LegacyImporter::class)->run(false);
            $this->fail('Corrupt payload should abort migration');
        } catch (DecryptException) {
            foreach (array_keys(LegacyImporter::TABLES) as $table) {
                $this->assertSame(0, DB::table($table)->count());
            }
        }
        $this->assertSame(1, DB::connection('legacy')->table('enerjisa_accounts')->count());
    }

    public function test_same_database_is_rejected(): void
    {
        config(['database.connections.legacy' => config('database.connections.pgsql')]);
        DB::purge('legacy');
        $this->expectException(RuntimeException::class);
        app(LegacyImporter::class)->run(false);
    }
}
