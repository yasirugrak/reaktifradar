<?php

namespace Tests\Feature;

use App\Enerjisa\Models\Account;
use App\Enerjisa\Models\Member;
use App\Enerjisa\Services\MdmClient;
use App\Enerjisa\Services\MdmException;
use App\Models\User;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnerjisaPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->travelTo(now()->setDate(2026, 9, 17)->startOfDay());
    }

    private function member(string $email = 'owner@example.test'): Member
    {
        $account = Account::create(['name' => 'Deneme Firma', 'client_id' => 'mdm-user', 'client_secret' => 'mdm-secret']);

        return Member::create(['account_id' => $account->id, 'name' => 'Deneme', 'email' => $email, 'password' => 'Password12345']);
    }

    private function fakeApi(array $payload): void
    {
        Http::fake([
            '*/oauth/token*' => Http::response(['access_token' => 'test-token', 'expires_in' => 299]),
            '*/customer/*' => Http::response($payload),
        ]);
    }

    public function test_guests_and_existing_panel_users_cannot_access_energy_accounts(): void
    {
        foreach (['/panel', '/panel/settings', '/panel/installations', '/panel/query', '/panel/results/1'] as $path) {
            $this->get($path)->assertRedirect('/panel/login');
        }
        $this->actingAs(User::factory()->create(), 'web')->get('/panel')->assertRedirect('/panel/login');
        $this->get('/panel/login')->assertOk()->assertSee('Panel parolası');
        $this->get('/panel/register')->assertOk();
    }

    public function test_registration_creates_isolated_account_and_logs_in(): void
    {
        $this->post('/panel/register', [
            'company' => 'Yeni Firma', 'name' => 'Ada', 'email' => 'ADA@example.test',
            'password' => 'Password12345', 'password_confirmation' => 'Password12345',
        ])->assertRedirect('/panel/settings');
        $member = Member::firstOrFail();
        $this->assertAuthenticatedAs($member, 'enerjisa');
        $this->assertGuest('web');
        $this->assertSame('Yeni Firma', $member->account->name);
        $this->assertSame('ada@example.test', $member->email);
        $this->assertNotSame('Password12345', $member->getRawOriginal('password'));
    }

    public function test_login_logout_and_all_panel_pages(): void
    {
        $this->member();
        $this->post('/panel/login', ['email' => 'owner@example.test', 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->post('/panel/login', ['email' => 'OWNER@example.test', 'password' => 'Password12345'])->assertRedirect('/panel');
        foreach (['/panel', '/panel/settings', '/panel/installations', '/panel/query'] as $path) {
            $this->get($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->post('/panel/logout')->assertRedirect('/panel/login');
        $this->assertGuest('enerjisa');
    }

    public function test_credentials_are_encrypted_and_blank_password_keeps_existing_secret(): void
    {
        $member = $this->member();
        $this->actingAs($member, 'enerjisa')->put('/panel/settings', ['client_id' => 'new-user', 'password' => 'secret-value'])
            ->assertSessionHasNoErrors();
        $account = $member->account->fresh();
        $this->assertSame('secret-value', $account->client_secret);
        $this->assertNotSame('secret-value', DB::table('enerjisa_accounts')->value('client_secret'));
        $this->assertArrayNotHasKey('client_secret', $account->toArray());
        $this->get('/panel/settings')->assertDontSee('secret-value');
        $this->put('/panel/settings', ['client_id' => 'new-user', 'password' => ''])->assertSessionHasNoErrors();
        $this->assertSame('secret-value', $account->fresh()->client_secret);
        $this->put('/panel/settings', ['client_id' => 'other-user', 'password' => ''])->assertSessionHasErrors('password');
        $this->put('/panel/settings', ['client_id' => '', 'password' => 'must-not-flash'])->assertSessionHasErrors();
        $this->assertNull(session('_old_input.password'));
    }

    public function test_a_tenant_cannot_view_another_tenants_query(): void
    {
        $owner = $this->member('one@example.test');
        $other = $this->member('two@example.test');
        $query = $other->account->queries()->create(['kind' => 'installations', 'parameters' => [], 'payload' => [['installationNumber' => '999999']]]);
        $this->actingAs($owner, 'enerjisa')->get('/panel/results/'.$query->id)->assertNotFound();
        $this->get('/panel/installations')->assertDontSee('999999');
        $this->get('/panel')->assertDontSee('/panel/results/'.$query->id, false);
    }

    public function test_connection_test_uses_documented_post_query_and_does_not_store_token(): void
    {
        $member = $this->member();
        $this->fakeApi([]);
        $this->actingAs($member, 'enerjisa')->post('/panel/connection')->assertSessionHasNoErrors();
        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return $request->method() === 'POST'
                && str_starts_with($request->url(), 'https://mdmsaatlik.baskentedas.com.tr/baskent/mdm-api/oauth/token?')
                && $query['grant_type'] === 'client_credentials'
                && $query['client_id'] === 'mdm-user' && $query['client_secret'] === 'mdm-secret'
                && $query['consumerID'] === 'MDMAYPRD';
        });
        $this->assertNotNull($member->account->fresh()->connected_at);
        $this->assertStringNotContainsString('test-token', $member->account->fresh()->toJson());
    }

    public function test_installations_are_saved_and_displayed_with_escaped_content(): void
    {
        $member = $this->member();
        $this->fakeApi(['Status' => 0, 'installationList' => [['installationNumber' => '001234', 'customerName' => '<script>alert(1)</script>']]]);
        $this->actingAs($member, 'enerjisa')->post('/panel/installations')->assertRedirect();
        $query = $member->account->queries()->firstOrFail();
        $this->get('/panel/results/'.$query->id)->assertOk()->assertSee('001234')->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/panel/installations')->assertOk()->assertSee('001234');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'installation-list') && $request->hasHeader('consumerID', 'MDM') && $request['consumerID'] === 'MDMAYPRD');
    }

    public function test_live_installation_spelling_is_read_from_saved_results_without_refetching(): void
    {
        $member = $this->member();
        $payload = [
            'status' => 0,
            'message' => 'Başarılı İşlem.',
            'instalation_list' => [
                ['instalationNumber' => '001234', 'customerName' => 'Test Tesisatı A', 'meterNumber' => '00005678'],
                ['instalationNumber' => '009876', 'customerName' => 'Test Tesisatı B', 'meterNumber' => '00004321'],
            ],
        ];
        $query = $member->account->queries()->create(['kind' => 'installations', 'parameters' => [], 'payload' => $payload]);
        $this->actingAs($member, 'enerjisa');
        $this->get('/panel/results/'.$query->id)->assertOk()->assertSee('2 görüntülenebilir kayıt alındı.')->assertSee('001234')->assertSee('009876');
        $this->get('/panel/installations')->assertOk()->assertSee('2 tesisat')->assertSee('Test Tesisatı A')->assertSee('Test Tesisatı B');
        $this->get('/panel/query')->assertOk()->assertSee('value="001234"', false)->assertSee('value="009876"', false);
        $this->get('/panel')->assertOk()->assertViewHas('installationCount', 2);
        $this->assertSame($payload, $query->fresh()->payload);
        Http::assertNothingSent();
    }

    public function test_hourly_query_and_token_renewal_use_correct_parameters(): void
    {
        $member = $this->member();
        Http::fake([
            '*/oauth/token*' => Http::sequence()->push(['access_token' => 'token-one'])->push(['access_token' => 'token-two']),
            '*/customer/*' => Http::sequence()->push([], 401)->push(['Status' => 0, 'valueList' => [['meterDate' => '2026-08-01 00:00:00', 'activeConsumption' => 8.8125, 'activeGeneration' => 0]]]),
        ]);
        $this->actingAs($member, 'enerjisa')->post('/panel/query', ['kind' => 'hourly', 'installation' => '00123', 'month' => '2026-08', 'from_date' => '2026-08-01T00:00'])->assertSessionHasNoErrors();
        Http::assertSent(fn ($request) => str_contains($request->url(), 'hourly-meter-information-multi-installation')
            && $request['installationNumbers'] === '00123' && $request['meterMonth'] === '2026-08'
            && $request['fromDate'] === '2026-08-01 00:00:00' && $request['access_token'] === 'token-two');
        Http::assertSentCount(4);
        $query = $member->account->queries()->firstOrFail();
        $this->get('/panel/results/'.$query->id)->assertOk()->assertSee('8.8125');
    }

    public function test_hourly_indices_accept_today_and_send_current_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-21 14:15:00', 'Europe/Istanbul'));
        Http::fake(['*oauth/token*' => Http::response(['access_token' => 'fake-token']), '*energy-value*' => Http::response([])]);
        $this->actingAs($this->member(), 'enerjisa')->post('/panel/query', [
            'kind' => '1', 'installation' => '123', 'start' => '2026-09-01', 'end' => '2026-09-21',
        ])->assertSessionHasNoErrors();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'energy-value') && $r['endDate'] === '21/09/2026 14:15:00');
        $this->post('/panel/query', [
            'kind' => '1', 'installation' => '123', 'start' => '2026-09-01', 'end' => '2026-09-22',
        ])->assertSessionHasErrors('end');
    }

    public function test_hourly_query_defaults_delta_to_selected_month_start(): void
    {
        $this->fakeApi(['status' => 0, 'valueList' => []]);
        $this->actingAs($this->member(), 'enerjisa')->post('/panel/query', [
            'kind' => 'hourly', 'installation' => '00123', 'month' => '2026-08',
        ])->assertSessionHasNoErrors();
        Http::assertSent(fn ($request) => str_contains($request->url(), 'hourly-meter-information-multi-installation')
            && $request['meterMonth'] === '2026-08' && $request['fromDate'] === '2026-08-01 00:00:00');
    }

    public static function unexpectedResponses(): array
    {
        return [
            'empty body' => ['', 'application/json', 'empty', 'boş yanıt'],
            'html gateway' => ['<!DOCTYPE html><html>mdm-secret test-token</html>', 'text/html; charset=UTF-8', 'html', 'HTML sayfası'],
            'truncated json' => ['{"secret":"mdm-secret",', 'application/json', 'invalid_json', 'geçersiz JSON'],
            'plain text' => ['mdm-secret test-token error', 'text/plain', 'invalid_json', 'geçersiz JSON'],
            'json null' => ['null', 'application/json', 'json_scalar', 'JSON nesnesi veya listesi'],
        ];
    }

    #[DataProvider('unexpectedResponses')]
    public function test_unexpected_hourly_responses_have_safe_diagnostics(string $body, string $contentType, string $kind, string $message): void
    {
        config(['enerjisa.debug' => true]);
        Log::spy();
        $member = $this->member();
        Http::fake([
            '*/oauth/token*' => Http::response(['access_token' => 'test-token']),
            '*/customer/*' => Http::response($body, 200, ['Content-Type' => $contentType]),
        ]);
        $this->actingAs($member, 'enerjisa')->post('/panel/query', [
            'kind' => 'hourly', 'installation' => '00123', 'month' => '2026-08',
        ])->assertRedirect()->assertSessionHas('enerjisa_diagnostics.response_kind', $kind)
            ->assertSessionHas('enerjisa_diagnostics.response_bytes', strlen($body));
        $query = $member->account->queries()->sole();
        $this->assertNull($query->payload);
        $this->assertStringContainsString($message, $query->error);
        $details = session('enerjisa_diagnostics');
        $this->assertStringNotContainsString('mdm-secret', json_encode($details));
        $this->assertStringNotContainsString('test-token', json_encode($details));
        $this->get('/panel/results/'.$query->id)->assertOk()->assertSee($message)->assertDontSee('mdm-secret')->assertDontSee('test-token');
        Log::shouldHaveReceived('warning')->once()->with('Enerjisa MDM diagnostic', $details);
    }

    public function test_energy_dates_are_serialized_according_to_the_guide(): void
    {
        $member = $this->member();
        $this->fakeApi(['Status' => 0, 'values' => [['meter_date' => '01/08/2026 00:00:00', 't_top_kWh' => '623,858.625']]]);
        $this->actingAs($member, 'enerjisa')->post('/panel/query', ['kind' => '2', 'installation' => '00123', 'start' => '2026-08-01', 'end' => '2026-08-31'])->assertSessionHasNoErrors();
        Http::assertSent(fn ($request) => str_contains($request->url(), 'energy-value') && $request['startDate'] === '01/08/2026 00:00:00' && $request['endDate'] === '31/08/2026 23:59:59' && (string) $request['dataType'] === '2');
    }

    public static function invalidRanges(): array
    {
        return [
            'month too long' => [['kind' => '1', 'start' => '2026-07-01', 'end' => '2026-08-01']],
            'year too long' => [['kind' => '3', 'start' => '2025-08-01', 'end' => '2026-08-01']],
            'today' => [['kind' => '2', 'start' => '2026-09-01', 'end' => '2026-09-17']],
            'reversed' => [['kind' => '2', 'start' => '2026-08-10', 'end' => '2026-08-01']],
            'delta month' => [['kind' => 'hourly', 'month' => '2026-08', 'from_date' => '2026-07-01T00:00']],
            'future month' => [['kind' => 'hourly', 'month' => '2026-10']],
            'invalid date' => [['kind' => '1', 'start' => '2026-02-30', 'end' => '2026-03-01']],
        ];
    }

    #[DataProvider('invalidRanges')]
    public function test_invalid_ranges_never_call_the_api(array $data): void
    {
        $this->actingAs($this->member(), 'enerjisa')->post('/panel/query', $data + ['installation' => '123'])->assertSessionHasErrors();
        Http::assertNothingSent();
        $this->assertDatabaseCount('enerjisa_queries', 0);
    }

    public function test_service_errors_in_http_200_are_recorded_as_failure(): void
    {
        $member = $this->member();
        $this->fakeApi(['Status' => [['Message_type' => '1', 'Message_text' => 'sensitive provider message mdm-secret']]]);
        $this->actingAs($member, 'enerjisa')->post('/panel/installations');
        $query = $member->account->queries()->firstOrFail();
        $this->assertNotNull($query->error);
        $this->assertNull($query->payload);
        $this->get('/panel/results/'.$query->id)->assertSee('yetkiniz bulunmuyor')->assertDontSee('mdm-secret');
    }

    public function test_sensitive_response_fields_are_removed_before_storage(): void
    {
        $member = $this->member();
        $this->fakeApi(['access_token' => 'test-token', 'nested' => ['client_secret' => 'mdm-secret'], 'message' => 'token test-token and mdm-secret']);
        $payload = app(MdmClient::class)->fetch($member->account, 'installations');
        $this->assertStringNotContainsString('test-token', json_encode($payload));
        $this->assertStringNotContainsString('mdm-secret', json_encode($payload));
        $this->assertArrayNotHasKey('access_token', $payload);
    }

    public function test_debug_diagnostics_identify_timeout_without_exposing_credentials(): void
    {
        config(['enerjisa.debug' => true]);
        Log::spy();
        $previous = new ConnectException(
            'secret-url', new Request('POST', 'https://example.test?client_secret=private-value'), null,
            ['errno' => 28, 'total_time' => 10.01, 'error' => 'private-value', 'url' => 'private-value'],
        );
        Http::fake(fn () => throw new ConnectionException('cURL error 28 private-value', 0, $previous));
        $this->actingAs($this->member(), 'enerjisa')->from('/panel/settings')->post('/panel/connection')
            ->assertSessionHasErrors('connection')->assertSessionHas('enerjisa_diagnostics.curl_errno', 28);
        $details = session('enerjisa_diagnostics');
        $this->assertSame('Zaman aşımı', $details['category']);
        $this->assertSame(10.01, $details['total_time']);
        $this->assertStringNotContainsString('private-value', json_encode($details));
        $this->get('/panel/settings')->assertSee('Ölçüm servisi bağlantı tanısı')->assertSee('Zaman aşımı')->assertDontSee('private-value');
        Log::shouldHaveReceived('warning')->once()->with('Enerjisa MDM diagnostic', $details);
    }

    public function test_debug_http_failure_has_status_but_no_response_body(): void
    {
        config(['enerjisa.debug' => true]);
        Log::spy();
        Http::fake(['*' => Http::response(['error' => 'mdm-secret'], 403)]);
        try {
            app(MdmClient::class)->token($this->member()->account);
            $this->fail('Expected a service exception.');
        } catch (MdmException $e) {
            $this->assertSame(403, $e->diagnostics['http_status']);
            $this->assertStringNotContainsString('mdm-secret', json_encode($e->diagnostics));
        }
    }

    public function test_debug_disabled_has_no_diagnostics_or_logs(): void
    {
        config(['enerjisa.debug' => false]);
        Log::spy();
        Http::fake(fn () => throw new ConnectionException('cURL error 6 private-value'));
        try {
            app(MdmClient::class)->token($this->member()->account);
            $this->fail('Expected a service exception.');
        } catch (MdmException $e) {
            $this->assertSame([], $e->diagnostics);
        }
        Log::shouldNotHaveReceived('warning');
    }

    public function test_csv_download_includes_all_pages_and_all_columns(): void
    {
        $member = $this->member();
        $records = [];
        for ($i = 0; $i < 125; $i++) {
            $records[] = ['meterDate' => '2026-08-'.str_pad((string) (($i % 31) + 1), 2, '0', STR_PAD_LEFT), 'activeConsumption' => $i];
        }
        $records[124]['extraColumn'] = 'Son kayıt';
        $query = $member->account->queries()->create(['kind' => 'hourly', 'parameters' => [], 'payload' => ['valueList' => $records]]);
        $this->actingAs($member, 'enerjisa');
        $response = $this->get('/panel/results/'.$query->id.'/download/csv?page=2')
            ->assertOk()->assertDownload('enerjisa-sorgu-'.$query->id.'.csv')
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertHeader('Cache-Control', 'no-store, private');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = explode("\r\n", trim(substr($csv, 3)));
        $this->assertCount(126, $lines);
        $this->assertSame(['meterDate', 'activeConsumption', 'extraColumn'], str_getcsv($lines[0], ';', '"', ''));
        $this->assertSame(['2026-08-01', '124', 'Son kayıt'], str_getcsv($lines[125], ';', '"', ''));
        Http::assertNothingSent();
    }

    public function test_installation_csv_quotes_values_and_neutralizes_formulas(): void
    {
        $member = $this->member();
        $query = $member->account->queries()->create(['kind' => 'installations', 'parameters' => [], 'payload' => ['instalation_list' => [[
            'instalationNumber' => '001234', 'customerName' => 'Çınar; "Tesis"',
            'untrusted' => '=1+1', 'spaced' => '  @SUM(1)', 'reading' => -12.5, 'enabled' => false,
        ]]]]);
        $this->actingAs($member, 'enerjisa');
        $csv = $this->get('/panel/results/'.$query->id.'/download/csv')->assertOk()->streamedContent();
        $lines = explode("\r\n", substr($csv, 3));
        $values = str_getcsv($lines[1], ';', '"', '');
        $this->assertSame(['001234', 'Çınar; "Tesis"', "'=1+1", "'  @SUM(1)", '-12.5', 'false'], $values);
    }

    public function test_json_download_preserves_the_entire_saved_payload_even_without_table_rows(): void
    {
        $member = $this->member();
        $payload = ['status' => 0, 'unknownStructure' => ['text' => 'Türkçe', 'values' => [0, null, false]]];
        $query = $member->account->queries()->create(['kind' => 'hourly', 'parameters' => [], 'payload' => $payload]);
        $this->actingAs($member, 'enerjisa');
        $response = $this->get('/panel/results/'.$query->id.'/download/json')->assertOk()->assertDownload('enerjisa-sorgu-'.$query->id.'.json');
        $this->assertSame($payload, json_decode($response->streamedContent(), true));
        $this->get('/panel/results/'.$query->id)->assertSee('JSON indir')->assertDontSee('CSV indir');
        $this->get('/panel/results/'.$query->id.'/download/csv')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_downloads_enforce_login_tenant_ownership_and_valid_formats(): void
    {
        $owner = $this->member('download-owner@example.test');
        $other = $this->member('download-other@example.test');
        $query = $owner->account->queries()->create(['kind' => 'installations', 'parameters' => [], 'payload' => [['instalationNumber' => '00123']]]);
        foreach (['csv', 'json'] as $format) {
            $this->get('/panel/results/'.$query->id.'/download/'.$format)->assertRedirect('/panel/login');
        }
        $this->actingAs($other, 'enerjisa');
        foreach (['csv', 'json'] as $format) {
            $this->get('/panel/results/'.$query->id.'/download/'.$format)->assertNotFound();
        }
        $this->actingAs($owner, 'enerjisa');
        $this->get('/panel/results/'.$query->id.'/download/xml')->assertNotFound();
        $this->get('/panel/results/'.$query->id)->assertSee('CSV indir')->assertSee('JSON indir');
    }

    public function test_failed_and_pending_queries_cannot_be_downloaded(): void
    {
        $member = $this->member();
        $this->actingAs($member, 'enerjisa');
        foreach ([null, 'Servis hatası'] as $error) {
            $query = $member->account->queries()->create(['kind' => 'hourly', 'parameters' => [], 'error' => $error]);
            foreach (['csv', 'json'] as $format) {
                $this->get('/panel/results/'.$query->id.'/download/'.$format)->assertNotFound();
            }
            $this->get('/panel/results/'.$query->id)->assertDontSee('CSV indir')->assertDontSee('JSON indir');
        }
    }

    public function test_network_exception_does_not_expose_request_url(): void
    {
        Http::fake(fn () => throw new ConnectionException('https://example.test?client_secret=private-value'));
        try {
            app(MdmClient::class)->token($this->member()->account);
            $this->fail('Expected a safe service exception.');
        } catch (MdmException $e) {
            $this->assertStringNotContainsString('private-value', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
    }
}
