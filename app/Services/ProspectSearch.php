<?php

namespace App\Services;

use App\Models\ProspectScan;
use App\Exceptions\ProspectSearchException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProspectSearch
{
    public static function configured(string $provider): bool
    {
        return match ($provider) {
            'website' => true,
            'serper' => (bool) config('prospecting.serper_key'),
            'google' => (bool) (config('prospecting.google_key') && config('prospecting.google_cx')),
            default => false,
        };
    }

    public function discover(ProspectScan $scan): array
    {
        $provider = $scan->provider ?? 'google';
        if (! self::configured($provider)) {
            throw new ProspectSearchException('Seçilen arama servisinin API ayarları eksik veya servis geçersiz.');
        }
        $serper = $provider === 'serper';
        $label = $serper ? 'Serper' : 'Google';
        $key = config($serper ? 'prospecting.serper_key' : 'prospecting.google_key');
        $cx = (string) config('prospecting.google_cx');
        if (! $serper && (! $key || ! $cx)) {
            throw new RuntimeException('Google Custom Search API anahtarı ve arama motoru kimliği (cx) tanımlanmalıdır.');
        }
        $query = trim("{$scan->region} {$scan->osb} {$scan->sector} organize sanayi fabrika üretim firma iletişim");
        $limit = min(60, max(1, (int) $scan->limit));
        $results = [];
        for ($start = 1; $start <= $limit; $start += 10) {
            try {
                $request = Http::timeout(8)->connectTimeout(3);
                $response = $serper
                    ? $request->withHeaders(['X-API-KEY' => $key])->post('https://google.serper.dev/search', [
                        'q' => $query, 'gl' => 'tr', 'hl' => 'tr', 'num' => 10,
                        'page' => intdiv($start - 1, 10) + 1,
                    ])
                    : $request->get('https://customsearch.googleapis.com/customsearch/v1', [
                        'key' => $key, 'cx' => $cx, 'q' => $query,
                        'gl' => 'tr', 'lr' => 'lang_tr',
                        'num' => min(10, $limit - $start + 1), 'start' => $start,
                    ]);
            } catch (ConnectionException) {
                // Do not propagate the request URL containing the API key to queue logs.
                throw new ProspectSearchException($label.' arama servisine bağlanılamadı.');
            }
            if (! $response->successful()) {
                $detail = $response->json($serper ? 'message' : 'error.message');
                $detail = is_string($detail) ? $detail : 'API erişimini, anahtarı, cx değerini ve kotayı kontrol edin.';
                $detail = str_replace(array_filter([$key, $cx, rawurlencode($key), rawurlencode($cx)]), '[gizli]', $detail);
                $detail = preg_replace('~https?://[^\s]+~i', '[bağlantı]', $detail);
                $detail = preg_replace('/[\x00-\x1F\x7F]/', ' ', $detail);
                throw new ProspectSearchException($label.' HTTP '.$response->status().': '.mb_substr($detail, 0, 220));
            }
            $items = $response->json($serper ? 'organic' : 'items', []);
            foreach ($items as $item) {
                $url = $item['link'] ?? '';
                $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
                if ($host && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https']) && ! isset($results[$host])) {
                    $results[$host] = ['url' => $url, 'company' => mb_substr($item['title'] ?? $host, 0, 255)];
                }
            }
            if (! $items || (! $serper && ! $response->json('queries.nextPage')) || ($serper && count($items) < 10)) {
                break;
            }
        }

        return array_slice(array_values($results), 0, $limit);
    }
}
