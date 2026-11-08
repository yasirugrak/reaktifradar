<?php
namespace Tests\Feature;
use App\Accounting\ExchangeRates;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class ExchangeRatesTest extends TestCase {
 public function test_rates_are_cached_and_retained_when_source_fails(): void {
 Cache::flush();
 Http::fake(['*'=>Http::sequence()->push('<Tarih_Date Tarih="06.11.2026"><Currency CurrencyCode="USD"><Unit>1</Unit><ForexBuying>42.50</ForexBuying><ForexSelling>42.60</ForexSelling></Currency><Currency CurrencyCode="EUR"><Unit>1</Unit><ForexBuying>49.10</ForexBuying><ForexSelling>49.20</ForexSelling></Currency></Tarih_Date>')->push('',503)]);
 $service=new ExchangeRates;
 $data=$service->latest();
 $this->assertSame(42.5,$data['rates']['USD']['buy']);
 $this->assertSame('06.11.2026',$data['date']);
 $this->assertFalse($data['stale']);
 $service->latest(); Http::assertSentCount(1);
 Cache::forget('accounting.rates.refresh');

 $fallback=$service->latest();
 $this->assertTrue($fallback['stale']);
 $this->assertSame($data['rates'],$fallback['rates']);
 }
}
