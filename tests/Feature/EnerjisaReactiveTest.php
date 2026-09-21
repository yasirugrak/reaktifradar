<?php

namespace Tests\Feature;

use App\Enerjisa\Models\Account;
use App\Enerjisa\Models\Member;
use App\Enerjisa\Services\Reactive\Readings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnerjisaReactiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-20 14:00:00', 'Europe/Istanbul'));
        Http::preventStrayRequests();
    }

    private function member(string $email = 'reactive@example.test'): Member
    {
        $account = Account::create(['name' => 'Reaktif Test']);

        return Member::create(['account_id' => $account->id, 'name' => 'Test', 'email' => $email, 'password' => 'Test12345']);
    }

    private function snapshot(Account $account, string $meter = '001', string $installation = '123', string $kind = '1'): void
    {
        $account->queries()->create(['kind' => $kind, 'parameters' => ['installationNumber' => $installation], 'payload' => ['values' => [
            ['meter_serial_no' => $meter, 'meter_date' => '01/08/2026 00:00:00', 't_top_kWh' => '1,000.000', 't_ri_kVarh' => '100', 't_rc_kVarh' => '100'],
            ['meter_serial_no' => $meter, 'meter_date' => '01/08/2026 01:00:00', 't_top_kWh' => '1,100.000', 't_ri_kVarh' => '121', 't_rc_kVarh' => '116'],
            ['meter_serial_no' => $meter, 'meter_date' => '02/08/2026 00:00:00', 't_top_kWh' => '1,200.000', 't_ri_kVarh' => '140', 't_rc_kVarh' => '130'],
        ]]]);
    }

    public function test_report_displays_hourly_and_daily_calculations_from_saved_indices(): void
    {
        $member = $this->member();
        $this->snapshot($member->account);
        $this->actingAs($member, 'enerjisa');
        $this->get('/panel/reactive?installation=123&period=hourly&start=2026-08-01&end=2026-08-01')
            ->assertOk()->assertSee('%21,000')->assertSee('%16,000')->assertSee('Eşik aşımı')
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 24 && $summary['alerts'] === 1);
        $this->get('/panel/reactive?installation=123&period=daily&start=2026-08-01&end=2026-08-01')
            ->assertOk()->assertSee('%20,000')->assertSee('%15,000')->assertSee('Eşikler içinde');
        Http::assertNothingSent();
    }

    public function test_tenant_and_meter_series_stay_separate(): void
    {
        $member = $this->member();
        $other = $this->member('other-reactive@example.test');
        $this->snapshot($other->account, 'secret-other-meter');
        $this->snapshot($member->account, '001');
        $this->snapshot($member->account, '002');
        $this->actingAs($member, 'enerjisa')->get('/panel/reactive?installation=123&period=daily&start=2026-08-01&end=2026-08-01&meter=002')
            ->assertOk()->assertDontSee('secret-other-meter')
            ->assertViewHas('meters', fn ($meters) => count($meters) === 2)
            ->assertViewHas('rows', fn ($rows) => $rows[0]['first']->meter === '002' && $rows[0]['activeDelta'] === '200.000000000');
        $this->get('/panel/reactive?installation=999')->assertOk()->assertSee('saatlik endeks kaydı yok');
    }

    public function test_latest_snapshot_replaces_duplicates_and_other_query_kinds_are_not_used(): void
    {
        $member = $this->member();
        $this->snapshot($member->account);
        $this->snapshot($member->account);
        $this->snapshot($member->account, 'wrong-kind', '123', 'hourly');
        $date = CarbonImmutable::parse('2026-08-01');
        $loaded = (new Readings)->load($member->account, '123', $date, $date->addMonth());
        $this->assertCount(1, $loaded['meters']);
        $this->assertCount(3, $loaded['meters']['001']);
        $this->assertFalse($loaded['meters']['001'][0]->conflict);
        $this->assertSame($member->account->queries()->where('kind', '1')->max('id'), $loaded['meters']['001'][0]->queryId);
    }

    public function test_empty_state_guests_and_invalid_date_filters(): void
    {
        $this->get('/panel/reactive')->assertRedirect('/panel/login');
        $this->get('/panel/reactive/download')->assertRedirect('/panel/login');
        $this->actingAs($this->member(), 'enerjisa')->get('/panel/reactive')->assertOk()->assertSee('saatlik endeks kaydı yok');
        $this->get('/panel/reactive?period=hourly&start=2026-01-01&end=2026-08-01')->assertSessionHasErrors('end');
        $this->get('/panel/reactive?start=2026-09-01&end=2026-08-01')->assertSessionHasErrors('end');
    }

    public function test_alert_filter_and_csv_export_are_consistent(): void
    {
        $member = $this->member();
        $this->snapshot($member->account);
        $this->actingAs($member, 'enerjisa');
        $parameters = '?installation=123&period=hourly&start=2026-08-01&end=2026-08-01&status=alert';
        $this->get('/panel/reactive'.$parameters)->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
        $response = $this->get('/panel/reactive/download'.$parameters)->assertOk()->assertDownload('enerjisa-reaktif-analiz.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('21.000000000', $csv);
        $this->assertStringContainsString('16.000000000', $csv);
        $this->assertStringContainsString('Eşik aşımı', $csv);
        $this->assertCount(3, explode("\r\n", substr($csv, 3)));
    }

    public function test_calculate_fetches_missing_months_and_reuses_them_for_other_periods(): void
    {
        $member = $this->member();
        $member->account->update(['client_id' => 'example-user', 'client_secret' => 'example-secret']);
        Http::fake([
            '*oauth/token*' => Http::response(['access_token' => 'fake-token']),
            '*energy-value*' => Http::response(['values' => [
                ['meter_date' => '01/08/2026 00:00:00', 't_top_kWh' => '100', 't_ri_kVarh' => '10', 't_rc_kVarh' => '10'],
                ['meter_date' => '02/08/2026 00:00:00', 't_top_kWh' => '200', 't_ri_kVarh' => '31', 't_rc_kVarh' => '26'],
            ]]),
        ]);
        $filters = ['installation' => '123', 'period' => 'daily', 'start' => '2026-08-01', 'end' => '2026-08-31'];
        $response = $this->actingAs($member, 'enerjisa')->post('/panel/reactive', $filters)->assertRedirect()->assertSessionHasNoErrors();
        Http::assertSentCount(4);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'energy-value') && $r['endDate'] === '01/09/2026 23:59:59' && $r['dataType'] === 1);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('%21,000');
        $this->post('/panel/reactive', array_replace($filters, ['period' => 'monthly']))->assertSessionHasNoErrors();
        Http::assertSentCount(4);
        $this->post('/panel/reactive', array_replace($filters, ['period' => 'monthly', 'refresh' => '1']))->assertSessionHasNoErrors();
        Http::assertSentCount(6);
    }

    public function test_calculate_fetches_uncovered_days_and_refreshes_today_separately(): void
    {
        $member = $this->member();
        $member->account->update(['client_id' => 'example-user', 'client_secret' => 'example-secret']);
        $member->account->queries()->create(['kind' => '1', 'parameters' => [
            'installationNumber' => '123', 'startDate' => '01/09/2026 00:00:00', 'endDate' => '10/09/2026 23:59:59',
        ], 'payload' => []]);
        Http::fake(['*oauth/token*' => Http::response(['access_token' => 'fake-token']), '*energy-value*' => Http::response([])]);
        $this->actingAs($member, 'enerjisa')->post('/panel/reactive', [
            'installation' => '123', 'period' => 'monthly', 'start' => '2026-09-01', 'end' => '2026-09-20',
        ])->assertSessionHasNoErrors();
        Http::assertSentCount(4);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'energy-value') && $r['startDate'] === '20/09/2026 00:00:00' && $r['endDate'] === '20/09/2026 14:00:00');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'energy-value') && $r['startDate'] === '11/09/2026 00:00:00' && $r['endDate'] === '19/09/2026 23:59:59');
    }

    public function test_sync_errors_remain_on_analysis_and_do_not_mark_failed_ranges_as_covered(): void
    {
        $member = $this->member();
        $member->account->update(['client_id' => 'example-user', 'client_secret' => 'example-secret']);
        Http::fake(['*oauth/token*' => Http::response(['access_token' => 'fake-token']), '*energy-value*' => Http::response([], 503)]);
        $filters = ['installation' => '123', 'period' => 'daily', 'start' => '2026-08-01', 'end' => '2026-08-01'];
        $this->actingAs($member, 'enerjisa')->post('/panel/reactive', $filters)->assertSessionHasErrors('connection')
            ->assertRedirectContains('/panel/reactive?');
        $this->assertNotNull($member->account->queries()->first()->error);
        $this->post('/panel/reactive', $filters)->assertSessionHasErrors('connection');
        Http::assertSentCount(4);
    }

    public function test_today_restriction_preserves_history_and_other_errors_are_visible(): void
    {
        $member = $this->member();
        $member->account->update(['client_id' => 'example-user', 'client_secret' => 'example-secret']);
        Http::fake(['*oauth/token*' => Http::response(['access_token' => 'fake-token']),
            '*energy-value*' => Http::sequence()->push(['values' => [
                ['meter_date' => '19/09/2026 00:00:00', 't_top_kWh' => '100', 't_ri_kVarh' => '10', 't_rc_kVarh' => '10'],
                ['meter_date' => '19/09/2026 23:00:00', 't_top_kWh' => '200', 't_ri_kVarh' => '31', 't_rc_kVarh' => '26'],
            ]])->push(['Status' => 5])->push([], 503)]);
        $filters = ['installation' => '123', 'period' => 'daily', 'start' => '2026-09-19', 'end' => '2026-09-20'];
        $response = $this->actingAs($member, 'enerjisa')->post('/panel/reactive', $filters)->assertSessionHasNoErrors();
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('%21,000');
        $this->assertSame(1, $member->account->queries()->whereNull('error')->count());
        $this->post('/panel/reactive', $filters)->assertSessionHasErrors('connection');
        Http::assertSentCount(6);
    }

    public function test_installation_select_and_owner_names_are_scoped_to_the_account(): void
    {
        $member = $this->member();
        $this->snapshot($member->account);
        $member->account->queries()->create(['kind' => 'installations', 'parameters' => [], 'payload' => ['instalation_list' => [
            ['instalationNumber' => '123', 'customerName' => 'Birinci İşletme'],
            ['instalationNumber' => '456', 'customerName' => 'İkinci İşletme'],
        ]]]);
        $this->actingAs($member, 'enerjisa')->get('/panel/reactive?installation=456')->assertOk()
            ->assertSee('<select id="reactive-installation"', false)->assertViewHas('owner', 'İkinci İşletme');
        $this->get('/panel/query')->assertOk()->assertSee('<select id="installation"', false)->assertSee('Birinci İşletme');
        $query = $member->account->queries()->where('kind', '1')->first();
        $this->get('/panel/results/'.$query->id)->assertOk()->assertViewHas('owner', 'Birinci İşletme');
    }
}
