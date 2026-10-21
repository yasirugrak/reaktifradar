<?php

namespace Tests\Unit;

use App\Models\ProspectScan;
use App\Services\ProspectSearch;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ProspectSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['prospecting.google_key' => 'test-key', 'prospecting.google_cx' => 'test-cx']);
        Http::preventStrayRequests();
    }

    public function test_google_pagination_and_domain_deduplication(): void
    {
        Http::fakeSequence()
            ->push(['items' => [['link' => 'https://firma.test/contact', 'title' => 'Firma']], 'queries' => ['nextPage' => [['startIndex' => 11]]]])
            ->push(['items' => [['link' => 'https://firma.test/about'], ['link' => 'https://other.test', 'title' => 'Other']]]);
        $results = (new ProspectSearch)->discover((new ProspectScan)->forceFill(['region' => 'Kocaeli', 'limit' => 20]));
        $this->assertCount(2, $results);
        $this->assertSame('Firma', $results[0]['company']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://customsearch.googleapis.com/customsearch/v1')
            && $request['key'] === 'test-key' && $request['cx'] === 'test-cx'
            && $request['start'] === 11 && $request['num'] === 10);
    }

    public function test_empty_results_stop_pagination(): void
    {
        Http::fakeSequence()->push([]);
        $this->assertSame([], (new ProspectSearch)->discover((new ProspectScan)->forceFill(['limit' => 60])));
        Http::assertSentCount(1);
    }

    public function test_missing_engine_id_prevents_requests(): void
    {
        config(['prospecting.google_cx' => '']);
        Http::fake();
        try {
            (new ProspectSearch)->discover((new ProspectScan)->forceFill(['limit' => 20]));
            $this->fail('Missing cx should fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('cx', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_api_rejection_does_not_expose_credentials(): void
    {
        Http::fakeSequence()->push(['error' => ['message' => 'API denied test-key test-cx https://example.test/?key=test-key']], 403);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google HTTP 403: API denied [gizli] [gizli] [bağlantı]');
        (new ProspectSearch)->discover((new ProspectScan)->forceFill(['limit' => 20]));
    }
}
