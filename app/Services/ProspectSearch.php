<?php

namespace App\Services;

use App\Models\ProspectScan;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProspectSearch
{
    public function discover(ProspectScan $scan): array
    {
        $key = config('prospecting.brave_key');
        if (! $key) {
            throw new RuntimeException('Brave Search API anahtarı tanımlanmamış.');
        }
        $query = trim("{$scan->region} {$scan->osb} {$scan->sector} organize sanayi fabrika üretim firma iletişim");
        $results = [];
        for ($page = 0; $page < (int) ceil($scan->limit / 20); $page++) {
            $response = Http::timeout(12)->connectTimeout(5)->withHeaders(['X-Subscription-Token' => $key])
                ->get('https://api.search.brave.com/res/v1/web/search', ['q' => $query, 'country' => 'TR', 'search_lang' => 'tr', 'count' => 20, 'offset' => $page]);
            if (! $response->successful()) {
                throw new RuntimeException('Arama servisi yanıt vermedi; API anahtarını ve kotayı kontrol edin.');
            }
            foreach ($response->json('web.results', []) as $item) {
                $url = $item['url'] ?? '';
                $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
                if ($host && ! isset($results[$host])) {
                    $results[$host] = ['url' => $url, 'company' => mb_substr($item['title'] ?? $host, 0, 255)];
                }
            }
            if ($page + 1 < ceil($scan->limit / 20)) {
                usleep(1100000);
            }
        }

        return array_slice(array_values($results), 0, $scan->limit);
    }
}
