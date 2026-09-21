<?php

namespace Tests\Feature;

use App\Access\PanelRole;
use App\Enerjisa\Models\Account;
use App\Enerjisa\Models\Member;
use App\Enerjisa\Models\NotificationRule;
use App\Enerjisa\Models\SystemSetting;
use App\Enerjisa\Services\Notifications\Dispatcher;
use App\Enerjisa\Services\TelegramSettings;
use App\Filament\Pages\EnerjisaAdmin;
use App\Filament\Resources\EnerjisaAccountResource;
use App\Filament\Resources\EnerjisaMemberResource;
use App\Filament\Resources\EnerjisaMemberResource\Pages\CreateRecord;
use App\Filament\Resources\EnerjisaMemberResource\Pages\EditRecord;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class EnerjisaAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(PanelRole $role = PanelRole::SuperAdmin): User
    {
        $admin = User::factory()->create(['role' => $role, 'email_verified_at' => now()]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    public function test_support_cannot_access_admin_resources_or_page(): void
    {
        $this->actingAs($this->admin(PanelRole::Support));
        $this->get(EnerjisaAdmin::getUrl())->assertForbidden();
        $this->get(EnerjisaMemberResource::getUrl())->assertForbidden();
        $this->get(EnerjisaAccountResource::getUrl())->assertForbidden();
        $this->assertFalse(EnerjisaMemberResource::canCreate());
    }

    public function test_admin_sees_status_and_can_create_and_edit_members(): void
    {
        $this->actingAs($this->admin());
        $account = Account::create(['name' => 'Firma']);
        $this->get(EnerjisaAdmin::getUrl())->assertOk()->assertSee('Merkezi Telegram kurulumu');
        $this->get(EnerjisaMemberResource::getUrl())->assertOk();
        $this->get(EnerjisaAccountResource::getUrl())->assertOk();
        Livewire::test(CreateRecord::class)
            ->fillForm(['name' => 'Yeni Kullanıcı', 'email' => 'new-member@example.test', 'account_id' => $account->id,
                'is_active' => true, 'password' => 'Yeni123'])->call('create')->assertHasNoFormErrors();
        $member = Member::where('email', 'new-member@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Yeni123', $member->password));
        Livewire::test(EditRecord::class, ['record' => $member->id])
            ->assertFormSet(['password' => null])->fillForm(['is_active' => false, 'password' => ''])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($member->fresh()->is_active);
        $this->assertTrue(Hash::check('Yeni123', $member->fresh()->password));
    }

    public function test_suspended_member_and_company_cannot_login_or_keep_session(): void
    {
        $account = Account::create(['name' => 'Firma', 'is_active' => false]);
        $member = Member::create(['account_id' => $account->id, 'name' => 'User', 'email' => 'suspended@example.test', 'password' => 'Test123']);
        $this->post('/enerjisa/login', ['email' => $member->email, 'password' => 'Test123'])->assertSessionHasErrors('email');
        $this->actingAs($member, 'enerjisa')->get('/enerjisa')->assertRedirect('/enerjisa/login');
        $account->update(['is_active' => true]);
        $member->update(['is_active' => false]);
        $this->post('/enerjisa/login', ['email' => $member->email, 'password' => 'Test123'])->assertSessionHasErrors('email');
    }

    public function test_password_change_ends_existing_session_and_suspended_company_sends_nothing(): void
    {
        $account = Account::create(['name' => 'Firma']);
        $member = Member::create(['account_id' => $account->id, 'name' => 'User', 'email' => 'session@example.test', 'password' => 'Test123']);
        $this->actingAs($member, 'enerjisa')->get('/enerjisa')->assertOk();
        $member->update(['password' => 'Changed123']);
        $this->get('/enerjisa')->assertRedirect('/enerjisa/login');
        $account->update(['is_active' => false]);
        NotificationRule::create(['account_id' => $account->id, 'installation' => '123', 'enabled' => true, 'send_time' => '00:00', 'email_enabled' => true, 'email' => 'recipient@example.test']);
        Http::preventStrayRequests();
        $this->assertSame(0, app(Dispatcher::class)->run());
        $this->assertDatabaseCount('enerjisa_notification_deliveries', 0);
        $this->assertNotNull(SystemSetting::find('scheduler'));
    }

    public function test_admin_setup_discovers_bot_registers_webhook_and_never_exposes_token(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        config(['app.url' => 'https://example.test', 'enerjisa.telegram' => ['webhook_secret' => '']]);
        Http::fake([
            '*getMe' => Http::response(['ok' => true, 'result' => ['id' => 123, 'is_bot' => true, 'username' => 'central_bot']]),
            '*setWebhook' => Http::response(['ok' => true]),
        ]);
        Livewire::test(EnerjisaAdmin::class)->set('token', '123:super_secret_token')->call('saveTelegram')
            ->assertHasNoErrors()->assertSet('token', '')->assertSee('@central_bot')->assertDontSee('super_secret_token');
        $this->assertTrue(app(TelegramSettings::class)->ready());
        $this->assertSame($admin->id, SystemSetting::find('telegram')->updated_by);
        $this->assertStringNotContainsString('super_secret_token', DB::table('enerjisa_system_settings')->where('key', 'telegram')->value('value'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'setWebhook') && $r['url'] === 'https://example.test/enerjisa/telegram/webhook' && strlen($r['secret_token']) >= 32);
    }

    public function test_failed_telegram_setup_preserves_current_settings(): void
    {
        $admin = $this->admin();
        config(['app.url' => 'https://example.test']);
        SystemSetting::create(['key' => 'telegram', 'value' => ['token' => 'old-token', 'username' => 'old_bot', 'webhook_secret' => 'secret']]);
        Http::fake(['*getMe' => Http::response(['ok' => false], 401)]);
        $this->expectException(\RuntimeException::class);
        try {
            app(TelegramSettings::class)->connect('123:bad-token', $admin->id);
        } finally {
            $this->assertSame('old-token', app(TelegramSettings::class)->all()['token']);
        }
    }
}
