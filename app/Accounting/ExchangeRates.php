<?php
namespace App\Accounting;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ExchangeRates
{
    public function latest(): array
    {
        return Cache::remember('accounting.rates.refresh', 900, function () {
            try {
                $body = Http::connectTimeout(2)->timeout(5)->get('https://www.tcmb.gov.tr/kurlar/today.xml')->throw()->body();
                if (str_contains(strtoupper($body), '<!DOCTYPE')) {
                    throw new \RuntimeException('Unexpected XML document');
                }
                $xml = @simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET);
                if ($xml === false || !preg_match('/^\d{2}\.\d{2}\.\d{4}$/', (string) $xml['Tarih'])) {
                    throw new \RuntimeException('Invalid rate bulletin');
                }
                $rates = [];
                foreach ($xml->Currency as $currency) {
                    $code = (string) $currency['CurrencyCode'];
                    if (!in_array($code, ['USD', 'EUR'], true)) continue;
                    $unit = (float) $currency->Unit;
                    $buy = (float) $currency->ForexBuying;
                    $sell = (float) $currency->ForexSelling;
                    if ($unit <= 0 || $buy <= 0 || $sell <= 0) throw new \RuntimeException('Invalid rates');
                    $rates[$code] = ['buy' => $buy / $unit, 'sell' => $sell / $unit];
                }
                if (count($rates) !== 2) throw new \RuntimeException('Incomplete bulletin');
                $result = ['rates' => $rates, 'date' => (string) $xml['Tarih'], 'checked_at' => now()->timezone('Europe/Istanbul')->format('d.m.Y H:i'), 'stale' => false];
                Cache::put('accounting.rates.last', $result, now()->addDays(30));
                return $result;
            } catch (\Throwable $e) {
                return array_merge(Cache::get('accounting.rates.last', ['rates' => [], 'date' => null, 'checked_at' => null]), ['stale' => true]);
            }
        });
    }
}
