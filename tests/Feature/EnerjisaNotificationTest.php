<?php

namespace Tests\Feature;

use App\Enerjisa\Models\Account;
use App\Enerjisa\Models\Member;
use App\Enerjisa\Models\NotificationRule;
use App\Enerjisa\Services\Notifications\Dispatcher;
use App\Enerjisa\Services\Notifications\Sender;
use App\Enerjisa\Services\Notifications\Summary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EnerjisaNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-21 10:00:00', 'Europe/Istanbul'));
        Http::preventStrayRequests();
        config(['enerjisa.telegram.token' => '123:central-token', 'enerjisa.telegram.username' => 'example_bot', 'enerjisa.telegram.webhook_secret' => str_repeat('x', 32)]);
    }

    private function member(string $email = 'notify@example.test'): Member
    {
        $account = Account::create(['name' => 'Bildirim Firma', 'client_id' => 'mdm-user', 'client_secret' => 'mdm-password']);
        $account->queries()->create(['kind' => 'installations', 'parameters' => [], 'payload' => ['instalation_list' => [
            ['instalationNumber' => '123', 'customerName' => 'Örnek İşletme'],
        ]]]);

        return Member::create(['account_id' => $account->id, 'name' => 'Test', 'email' => $email, 'password' => 'Test12345']);
    }

    private function rule(Account $account, array $extra = []): NotificationRule
    {
        return NotificationRule::create(array_merge(['account_id' => $account->id, 'installation' => '123', 'enabled' => true,
            'frequency' => 'daily', 'send_time' => '09:00', 'weekday' => 1, 'send_healthy' => true,
            'email_enabled' => true, 'telegram_enabled' => true, 'email' => 'recipient@example.test', 'chat_id' => '100123', 'telegram_connected_at' => now()], $extra));
    }

    private function settings(): array
    {
        return ['smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_security' => 'starttls',
            'smtp_username' => 'mail-user', 'smtp_password' => 'secret-smtp-password',
            'from_address' => 'sender@example.test', 'from_name' => 'Enerji', 'telegram_token' => '123456:secret_bot_token'];
    }

    public function test_users_only_see_recipient_and_connect_button_not_technical_settings(): void
    {
        $member = $this->member();
        $this->actingAs($member, 'enerjisa')->get('/enerjisa/notifications?installation=123')->assertOk()
            ->assertSee('Telegram’ı bağla')->assertDontSee('name="smtp_host"', false)
            ->assertDontSee('name="telegram_token"', false)->assertDontSee('name="chat_id"', false);
        $this->post('/enerjisa/notifications/settings', $this->settings())->assertNotFound();
        $this->assertNull($member->account->fresh()->notification_settings);
    }

    public function test_rules_require_channels_and_own_installations(): void
    {
        $member = $this->member();
        $data = ['installation' => '123', 'enabled' => '1', 'frequency' => 'weekly', 'send_time' => '08:30',
            'weekday' => '1', 'send_healthy' => '1', 'email_enabled' => '1', 'telegram_enabled' => '0', 'email' => 'to@example.test'];
        $this->actingAs($member, 'enerjisa')->post('/enerjisa/notifications', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enerjisa_notification_rules', ['account_id' => $member->account_id, 'frequency' => 'weekly']);
        $other = $this->member('other-notify@example.test');
        $this->actingAs($other, 'enerjisa')->get('/enerjisa/notifications?installation=123')->assertViewHas('rule', null);
        $this->post('/enerjisa/notifications', array_replace($data, ['installation' => '999']))->assertSessionHasErrors('installation');
        $this->post('/enerjisa/notifications', array_replace($data, ['email_enabled' => '0']))->assertSessionHasErrors('channels');
    }

    public function test_guest_cannot_access_or_change_notifications(): void
    {
        $this->get('/enerjisa/notifications')->assertRedirect('/enerjisa/login');
        $this->post('/enerjisa/notifications', [])->assertRedirect('/enerjisa/login');
        $this->post('/enerjisa/notifications/telegram/connect', [])->assertRedirect('/enerjisa/login');
    }

    public function test_dispatcher_honours_weekday_time_and_disabled_rules(): void
    {
        $rule = $this->rule($this->member()->account, ['frequency' => 'weekly', 'weekday' => 2]);
        $this->mock(Sender::class)->shouldNotReceive('send');
        $this->assertSame(0, app(Dispatcher::class)->run());
        $rule->update(['weekday' => 1, 'send_time' => '11:00']);
        $this->assertSame(0, app(Dispatcher::class)->run());
        $rule->update(['enabled' => false, 'send_time' => '09:00']);
        $this->assertSame(0, app(Dispatcher::class)->run());
        $this->assertDatabaseCount('enerjisa_notification_deliveries', 0);
    }

    public function test_dispatcher_does_not_repeat_success_and_retries_only_failed_channel(): void
    {
        $rule = $this->rule($this->member()->account);
        $this->mock(Summary::class)->shouldReceive('build')->once()->andReturn(['healthy' => false, 'body' => 'Eşik aşımı', 'email' => ['state' => 'alert']]);
        $sender = $this->mock(Sender::class);
        $sender->shouldReceive('send')->withArgs(fn ($r, $channel, $body) => $channel === 'email')->once();
        $sender->shouldReceive('send')->withArgs(fn ($r, $channel, $body) => $channel === 'telegram')->once()->andThrow(new RuntimeException('secret error'));
        $this->assertSame(1, app(Dispatcher::class)->run());
        $this->assertSame(0, app(Dispatcher::class)->run());
        $this->travel(31)->minutes();
        $sender->shouldReceive('send')->withArgs(fn ($r, $channel, $body) => $channel === 'telegram')->once();
        $this->assertSame(1, app(Dispatcher::class)->run());
        $this->assertSame(0, app(Dispatcher::class)->run());
        $this->assertSame(2, $rule->deliveries()->where('status', 'sent')->count());
        $this->assertStringNotContainsString('Eşik aşımı', DB::table('enerjisa_notification_deliveries')->first()->body);
    }

    public function test_healthy_message_is_optional_but_data_problems_are_always_reported(): void
    {
        $rule = $this->rule($this->member()->account, ['send_healthy' => false]);
        $summary = $this->mock(Summary::class);
        $summary->shouldReceive('build')->once()->andReturn(['healthy' => true, 'body' => 'Sorun yok', 'email' => ['state' => 'healthy']]);
        $sender = $this->mock(Sender::class);
        $this->assertSame(0, app(Dispatcher::class)->run());
        $this->assertSame(2, $rule->deliveries()->where('status', 'skipped')->count());
        $this->travel(1)->days();
        $summary->shouldReceive('build')->once()->andReturn(['healthy' => false, 'body' => 'Veriler eksik', 'email' => ['state' => 'incomplete']]);
        $sender->shouldReceive('send')->twice();
        $this->assertSame(2, app(Dispatcher::class)->run());
    }

    public function test_summary_uses_real_daily_deltas_and_does_not_call_missing_data_healthy(): void
    {
        $rule = $this->rule($this->member()->account);
        $values = [
            ['meter_date' => '19/09/2026 00:00:00', 't_top_kWh' => '100', 't_ri_kVarh' => '10', 't_rc_kVarh' => '10'],
            ['meter_date' => '20/09/2026 00:00:00', 't_top_kWh' => '200', 't_ri_kVarh' => '31', 't_rc_kVarh' => '26'],
        ];
        Http::fake(['*oauth/token*' => Http::response(['access_token' => 'example-token']), '*energy-value*' => Http::sequence()->push(['values' => $values])->push([])]);
        $summary = app(Summary::class)->build($rule, CarbonImmutable::now('Europe/Istanbul'));
        $this->assertFalse($summary['healthy']);
        $this->assertStringContainsString('Endüktif %21,000', $summary['body']);
        $this->assertStringContainsString('Örnek İşletme', $summary['body']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'energy-value') && $r['endDate'] === '20/09/2026 23:59:59');
        $rule->account->queries()->where('kind', '1')->delete();
        $summary = app(Summary::class)->build($rule, CarbonImmutable::now('Europe/Istanbul'));
        $this->assertFalse($summary['healthy']);
        $this->assertStringContainsString('doğrulanamadı', $summary['body']);
    }

    public function test_weekly_summary_requires_all_seven_days_before_reporting_healthy(): void
    {
        $rule = $this->rule($this->member()->account, ['frequency' => 'weekly']);
        $values = [];
        for ($day = 13; $day <= 20; $day++) {
            $values[] = ['meter_date' => $day.'/09/2026 00:00:00', 't_top_kWh' => (string) ($day * 100),
                't_ri_kVarh' => (string) ($day * 20), 't_rc_kVarh' => (string) ($day * 15)];
        }
        Http::fake(['*oauth/token*' => Http::response(['access_token' => 'example-token']),
            '*energy-value*' => Http::sequence()->push(['values' => $values])->push(['Status' => 5])->push([], 503)]);
        $summary = app(Summary::class)->build($rule, CarbonImmutable::now('Europe/Istanbul'));
        $this->assertTrue($summary['healthy']);
        $this->assertStringContainsString('sorun yok', $summary['body']);
        $this->assertStringContainsString('13.09.2026 – 19.09.2026', $summary['body']);
        $summary = app(Summary::class)->build($rule, CarbonImmutable::now('Europe/Istanbul'));
        $this->assertFalse($summary['healthy']);
        $this->assertStringContainsString('Güncel veriler alınamadı', $summary['body']);
    }

    public function test_summary_uses_todays_latest_measurement_and_marks_partial_day(): void
    {
        $rule = $this->rule($this->member()->account);
        Http::fake(['*oauth/token*' => Http::response(['access_token' => 'example-token']),
            '*energy-value*' => Http::sequence()->push([])->push(['values' => [
                ['meter_date' => '21/09/2026 00:00:00', 't_top_kWh' => '100', 't_ri_kVarh' => '10', 't_rc_kVarh' => '10'],
                ['meter_date' => '21/09/2026 09:00:00', 't_top_kWh' => '200', 't_ri_kVarh' => '20', 't_rc_kVarh' => '20'],
            ]])]);
        $summary = app(Summary::class)->build($rule, CarbonImmutable::now('Europe/Istanbul'));
        $this->assertTrue($summary['healthy']);
        $this->assertSame('2026-09-21', $summary['email']['end']);
        $this->assertSame(1, $summary['email']['partial']);
        $this->assertSame('21.09.2026 09:00', $summary['email']['rows'][0]['lastTime']);
        $this->assertStringContainsString('günün tamamını kapsamaz', $summary['body']);
    }

    public function test_email_uses_central_smtp_even_if_account_contains_old_smtp_settings(): void
    {
        $rule = $this->rule($this->member()->account);
        $rule->account->update(['notification_settings' => $this->settings()]);
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true])]);
        app(Sender::class)->send($rule, 'telegram', 'Durum mesajı');
        Http::assertSent(fn ($r) => $r['chat_id'] === '100123' && str_contains($r->url(), 'bot123:central-token/') && $r['text'] === 'Durum mesajı' && ! isset($r['parse_mode']));
        $mailer = Mockery::mock(Mailer::class);
        $manager = $this->mock(MailManager::class);
        config(['mail.mailers.smtp.host' => 'smtp.example.test', 'mail.from.address' => 'central@example.test', 'mail.from.name' => 'Merkezi Gönderici']);
        $manager->shouldReceive('mailer')->once()->with('smtp')->andReturn($mailer);
        $manager->shouldNotReceive('build');
        $mailer->shouldReceive('send')->once()->withArgs(function ($views, $data, $callback) {
            $email = new Email;
            $callback(new Message($email));

            return $views['html'] === 'enerjisa.mail.report' && $views['text'] === 'enerjisa.mail.report-text' && $data['body'] === 'Durum mesajı' && str_contains($email->getSubject(), 'ReaktifRadar') && $email->getTo()[0]->getAddress() === 'recipient@example.test'
                && $email->getFrom()[0]->getAddress() === 'central@example.test';
        });
        app(Sender::class)->send($rule, 'email', 'Durum mesajı');
    }

    public function test_telegram_failure_never_exposes_token_in_error(): void
    {
        $rule = $this->rule($this->member()->account);
        $rule->account->update(['notification_settings' => $this->settings()]);
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'secret_bot_token'], 403)]);
        $this->expectExceptionMessage('Telegram gönderilemedi. Telegram bağlantınızı kontrol edin veya sistem yöneticisine başvurun.');
        app(Sender::class)->send($rule, 'telegram', 'Rapor');
    }

    private function telegramStart(string $token, string $chat = '987654'): array
    {
        return ['message' => ['text' => '/start '.$token, 'chat' => ['type' => 'private', 'id' => $chat], 'from' => ['id' => $chat]]];
    }

    public function test_telegram_link_is_scoped_expiring_single_use_and_disconnectable(): void
    {
        $member = $this->member();
        $response = $this->actingAs($member, 'enerjisa')->post('/enerjisa/notifications/telegram/connect', ['installation' => '123'])->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertStringStartsWith('https://t.me/example_bot?start=', $url);
        $token = substr($url, strpos($url, 'start=') + 6);
        $rule = NotificationRule::where('account_id', $member->account_id)->firstOrFail();
        $this->assertSame(hash('sha256', $token), $rule->telegram_link_hash);
        $this->postJson('/enerjisa/telegram/webhook', $this->telegramStart($token))->assertForbidden();
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', str_repeat('x', 32))
            ->postJson('/enerjisa/telegram/webhook', $this->telegramStart($token))->assertOk()->assertJsonPath('method', 'sendMessage');
        $this->assertSame('987654', $rule->fresh()->chat_id);
        $this->assertNotNull($rule->fresh()->telegram_connected_at);
        $this->get('/enerjisa/notifications?installation=123')->assertOk()->assertSee('Telegram bağlı');
        $this->assertNull($rule->fresh()->telegram_link_hash);
        $this->postJson('/enerjisa/telegram/webhook', $this->telegramStart($token, '111111'))->assertOk();
        $this->assertSame('987654', $rule->fresh()->chat_id);
        $other = $this->member('different@example.test');
        $this->actingAs($other, 'enerjisa')->post('/enerjisa/notifications/telegram/disconnect', ['installation' => '123']);
        $this->assertSame('987654', $rule->fresh()->chat_id);
        $this->actingAs($member, 'enerjisa')->post('/enerjisa/notifications/telegram/disconnect', ['installation' => '123'])->assertRedirect();
        $this->assertNull($rule->fresh()->chat_id);
        $this->assertFalse($rule->fresh()->telegram_enabled);
    }

    public function test_expired_telegram_link_and_group_chat_cannot_bind(): void
    {
        $member = $this->member();
        $rule = $this->rule($member->account, ['chat_id' => null, 'telegram_connected_at' => null,
            'telegram_link_hash' => hash('sha256', str_repeat('a', 64)), 'telegram_link_expires_at' => now()->subMinute()]);
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', str_repeat('x', 32))
            ->postJson('/enerjisa/telegram/webhook', $this->telegramStart(str_repeat('a', 64)))->assertOk();
        $this->assertNull($rule->fresh()->chat_id);
        $rule->update(['telegram_link_expires_at' => now()->addMinutes(15)]);
        $data = $this->telegramStart(str_repeat('a', 64));
        $data['message']['chat']['type'] = 'group';
        $this->postJson('/enerjisa/telegram/webhook', $data)->assertOk();
        $this->assertNull($rule->fresh()->chat_id);
    }

    public function test_stop_revokes_connected_telegram_and_user_cannot_set_chat_id_manually(): void
    {
        $member = $this->member();
        $rule = $this->rule($member->account);
        $data = ['installation' => '123', 'enabled' => '1', 'frequency' => 'daily', 'send_time' => '09:00',
            'weekday' => '1', 'send_healthy' => '1', 'email_enabled' => '0', 'telegram_enabled' => '1', 'chat_id' => '999999'];
        $this->actingAs($member, 'enerjisa')->post('/enerjisa/notifications', $data)->assertSessionHasNoErrors();
        $this->assertSame('100123', $rule->fresh()->chat_id);
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', str_repeat('x', 32))->postJson('/enerjisa/telegram/webhook', [
            'message' => ['text' => '/stop', 'chat' => ['type' => 'private', 'id' => '100123'], 'from' => ['id' => '100123']],
        ])->assertOk();
        $this->assertFalse($rule->fresh()->telegram_enabled);
        $this->assertNull($rule->fresh()->chat_id);
        $this->post('/enerjisa/notifications', $data)->assertSessionHasErrors('channels');
    }

    public function test_email_contains_designed_html_and_plain_text_alternative(): void
    {
        $rule = $this->rule($this->member()->account);
        config(['app.url' => 'https://example.test', 'mail.mailers.smtp' => ['transport' => 'array', 'host' => 'smtp.example.test'],
            'mail.from.address' => 'central@example.test', 'mail.from.name' => 'ReaktifRadar']);
        $report = ['state' => 'alert', 'installation' => '123', 'owner' => '<script>Örnek</script>', 'frequency' => 'daily',
            'start' => '2026-09-19', 'end' => '2026-09-19', 'period' => '19.09.2026 – 19.09.2026',
            'total' => 1, 'alerts' => 1, 'invalid' => 0, 'rows' => [
                ['day' => '19.09.2026', 'meter' => '001', 'status' => 'alert', 'message' => 'Eşik aşımı',
                    'inductive' => '21.500', 'capacitive' => '12.000', 'inductiveAlert' => true, 'capacitiveAlert' => false],
            ]];
        app(Sender::class)->send($rule, 'email', 'Düz metin raporu', $report);
        $message = app(MailManager::class)->mailer('smtp')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertStringContainsString('ReaktifRadar', $message->getSubject());
        $this->assertStringContainsString('Kontrol gerektiren değerler var.', $message->getHtmlBody());
        $this->assertStringContainsString('%21,500', $message->getHtmlBody());
        $this->assertStringContainsString('Ayrıntılı raporu incele', $message->getHtmlBody());
        $this->assertStringContainsString('&lt;script&gt;', $message->getHtmlBody());
        $this->assertStringNotContainsString('<script>', $message->getHtmlBody());
        $this->assertStringContainsString('Düz metin raporu', $message->getTextBody());
        $this->assertStringContainsString('multipart/alternative', $message->toString());
        foreach (['healthy' => 'Değerleriniz sınırlar içinde.', 'incomplete' => 'Durum henüz doğrulanamadı.', 'unavailable' => 'Güncel veriler alınamadı.'] as $state => $heading) {
            $html = view('enerjisa.mail.report', ['report' => array_replace($report, ['state' => $state]), 'body' => '', 'url' => 'https://example.test'])->render();
            $this->assertStringContainsString($heading, $html);
        }
    }
}
