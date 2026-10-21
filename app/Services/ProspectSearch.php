<?php

namespace App\Services;

use App\Models\ProspectScan;
use App\Exceptions\ProspectSearchException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProspectSearch
{
    public function discover(ProspectScan $scan): array
    {
        $key = config('prospecting.google_key');
        $cx = config('prospecting.google_cx');
        if (! $key || ! $cx) {
            throw new RuntimeException('Google Custom Search API anahtarı ve arama motoru kimliği (cx) tanımlanmalıdır.');
        }
        $query = trim("{$scan->region} {$scan->osb} {$scan->sector} organize sanayi fabrika üretim firma iletişim");
        $limit = min(60, max(1, (int) $scan->limit));
        $results = [];
        for ($start = 1; $start <= $limit; $start += 10) {
            try {
                $response = Http::timeout(8)->connectTimeout(3)
                    ->get('https://customsearch.googleapis.com/customsearch/v1', [
                        'key' => $key, 'cx' => $cx, 'q' => $query,
                        'gl' => 'tr', 'lr' => 'lang_tr',
                        'num' => min(10, $limit - $start + 1), 'start' => $start,
                    ]);
            } catch (ConnectionException) {
                // Do not propagate the request URL containing the API key to queue logs.
                throw new RuntimeException('Google arama servisine bağlanılamadı.');
            }
            if (! $response->successful()) {
                $detail = $response->json('error.message');
                $detail = is_string($detail) ? $detail : 'API erişimini, anahtarı, cx değerini ve kotayı kontrol edin.';
                $detail = str_replace(array_filter([$key, $cx, rawurlencode($key), rawurlencode($cx)]), '[gizli]', $detail);
                $detail = preg_replace('~https?://[^\s]+~i', '[bağlantı]', $detail);
                $detail = preg_replace('/[\x00-\x1F\x7F]/', ' ', $detail);
                throw new ProspectSearchException('Google HTTP '.$response->status().': '.mb_substr($detail, 0, 220));
            }
            $items = $response->json('items', []);
            foreach ($items as $item) {
                $url = $item['link'] ?? '';
                $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
                if ($host && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https']) && ! isset($results[$host])) {
                    $results[$host] = ['url' => $url, 'company' => mb_substr($item['title'] ?? $host, 0, 255)];
                }
            }
            if (! $items || ! $response->json('queries.nextPage')) {
                break;
            }
        }

        return array_slice(array_values($results), 0, $limit);
    }
}
