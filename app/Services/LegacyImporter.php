<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LegacyImporter
{
    public const TABLES = [
        'users' => [],
        'enerjisa_accounts' => ['client_id', 'client_secret', 'notification_settings'],
        'enerjisa_users' => [],
        'enerjisa_queries' => ['payload'],
        'enerjisa_notification_rules' => [],
        'enerjisa_notification_deliveries' => ['body', 'email_data'],
        'enerjisa_system_settings' => ['value'],
    ];

    /** @return array<string, int> */
    public function run(bool $dryRun = true): array
    {
        $source = DB::connection('legacy');
        $target = DB::connection();
        if ($source->getDriverName() !== 'pgsql' || $target->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Taşıma yalnızca PostgreSQL ile destekleniyor.');
        }
        // Reject even aliases that resolve to the same database on the same server.
        $identity = 'SELECT current_database() AS db, inet_server_addr()::text AS host, inet_server_port() AS port';
        if ($source->selectOne($identity) == $target->selectOne($identity)) {
            throw new RuntimeException('Kaynak ve hedef aynı veritabanı olamaz.');
        }
        $key = $this->decodeKey((string) config('legacy.key'));
        $decryptor = new Encrypter($key, 'AES-256-CBC');
        $decryptor->previousKeys(array_map($this->decodeKey(...), config('legacy.previous_keys', [])));

        return $source->transaction(function () use ($source, $target, $decryptor, $dryRun): array {
            $source->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            $source->statement("SET LOCAL TIME ZONE 'UTC'");

            return $target->transaction(function () use ($source, $target, $decryptor, $dryRun): array {
                $target->statement("SET LOCAL TIME ZONE 'UTC'");
                // Serialise imports and prevent target writes while validating/copying.
                foreach (array_keys(self::TABLES) as $table) {
                    $target->statement('LOCK TABLE "'.$table.'" IN EXCLUSIVE MODE');
                    if ($target->table($table)->exists()) {
                        throw new RuntimeException('Hedef tablolar boş olmalı; mevcut verilerin üzerine yazılmadı.');
                    }
                }
                $counts = [];
                $adminIds = $source->table('users')->where('role', 'super_admin')->pluck('id')->all();
                foreach (self::TABLES as $table => $encrypted) {
                    $primary = $table === 'enerjisa_system_settings' ? 'key' : 'id';
                    $query = $source->table($table);
                    if ($table === 'users') {
                        $query->where('role', 'super_admin');
                    }
                    $counts[$table] = 0;
                    $digest = hash_init('sha256');
                    foreach ($query->orderBy($primary)->lazy(100) as $record) {
                        $row = (array) $record;
                        if ($table === 'enerjisa_system_settings' && ! in_array($row['updated_by'], $adminIds)) {
                            $row['updated_by'] = null;
                        }
                        foreach ($encrypted as $column) {
                            if ($row[$column] !== null) {
                                $row[$column] = $decryptor->decryptString($row[$column]);
                            }
                        }
                        $this->digest($digest, $row);
                        if (! $dryRun) {
                            foreach ($encrypted as $column) {
                                if ($row[$column] !== null) {
                                    $row[$column] = app('encrypter')->encryptString($row[$column]);
                                }
                            }
                            $target->table($table)->insert($row);
                        }
                        $counts[$table]++;
                    }
                    if (! $dryRun) {
                        $check = hash_init('sha256');
                        foreach ($target->table($table)->orderBy($primary)->lazy(100) as $record) {
                            $row = (array) $record;
                            foreach ($encrypted as $column) {
                                if ($row[$column] !== null) {
                                    $row[$column] = app('encrypter')->decryptString($row[$column]);
                                }
                            }
                            $this->digest($check, $row);
                        }
                        if ($target->table($table)->count() !== $counts[$table] || hash_final($digest) !== hash_final($check)) {
                            throw new RuntimeException('Taşınan kayıtların içerik doğrulaması başarısız. İşlem geri alındı.');
                        }
                        if ($primary === 'id') {
                            $target->select("SELECT setval(pg_get_serial_sequence(?, 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 1), EXISTS(SELECT 1 FROM \"{$table}\"))", [$table]);
                        }
                    }
                }

                return $counts;
            });
        });
    }

    private function decodeKey(string $key): string
    {
        return str_starts_with($key, 'base64:') ? (string) base64_decode(substr($key, 7), true) : $key;
    }

    private function digest(\HashContext $context, array $row): void
    {
        ksort($row);
        hash_update($context, json_encode($row, JSON_THROW_ON_ERROR)."\n");
    }
}
