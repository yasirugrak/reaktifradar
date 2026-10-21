<?php

namespace App\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProspectCrawler
{
    public function publicAddress(string $url): array
    {
        $parts = parse_url($url);
        if (! $parts || ! in_array($parts['scheme'] ?? '', ['https', 'http'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new RuntimeException('Geçersiz web adresi.');
        }
        $host = $parts['host'] ?? '';
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(
            array_column(dns_get_record($host, DNS_A) ?: [], 'ip'),
            array_column(dns_get_record($host, DNS_AAAA) ?: [], 'ipv6')
        );
        if (! $ips) {
            throw new RuntimeException('Alan adı çözümlenemedi.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Özel ağ adresleri taranamaz.');
            }
        }

        return [$host, $ips[0], $parts['scheme'] === 'https' ? 443 : 80];
    }

    public function fetch(string $url): string
    {
        for ($redirect = 0; $redirect < 3; $redirect++) {
            [$host, $ip, $port] = $this->publicAddress($url);
            $response = Http::timeout(6)->connectTimeout(3)->withHeaders(['User-Agent' => 'ReaktifRadarContactBot/1.0'])
                ->withOptions(['allow_redirects' => false, 'proxy' => '', 'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:".(str_contains($ip, ':') ? "[{$ip}]" : $ip)]],
                    'on_headers' => function ($response) {
                        if ((int) $response->getHeaderLine('Content-Length') > 2000000) {
                            throw new RuntimeException('Sayfa çok büyük.');
                        }
                    },
                    'progress' => function ($total, $downloaded) {
                        if ($downloaded > 2000000) {
                            throw new RuntimeException('Sayfa çok büyük.');
                        }
                    },
                ])->get($url);
            if ($response->redirect()) {
                $url = (string) UriResolver::resolve(new Uri($url), new Uri($response->header('Location')));

                continue;
            }
            if (! $response->successful()) {
                throw new RuntimeException('Sayfa okunamadı.');
            }
            if (! str_contains($response->header('Content-Type'), 'text/')) {
                return '';
            }

            return $response->body();
        }
        throw new RuntimeException('Çok fazla yönlendirme.');
    }

    public function emails(string $html, bool $sharedOnly = true): array
    {
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);

        return array_values(array_unique(array_filter(array_map('strtolower', $matches[0]), function ($email) use ($sharedOnly) {
            [$local, $host] = explode('@', $email, 2);

            return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL)
                && (! $sharedOnly || preg_match('/^(info|bilgi|iletisim|contact|sales|satis|export|ihracat|office|ofis|marketing|pazarlama|genel|santral)([._-].*)?$/', $local))
                && (! $sharedOnly || ! in_array($host, ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com', 'example.com'], true))
                && ! preg_match('/\.(png|jpg|jpeg|gif|webp|svg)$/', $host);
        })));
    }

    public function siteLinks(string $html, string $base): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $links = [];
        foreach ($dom->getElementsByTagName('a') as $a) {
            try {
                $uri = UriResolver::resolve(new Uri($base), new Uri($a->getAttribute('href')))->withFragment('');
                if (! in_array($uri->getScheme(), ['http', 'https']) || $uri->getHost() !== parse_url($base, PHP_URL_HOST)
                    || $uri->getUserInfo() || $uri->getPort() || $uri->getQuery()
                    || preg_match('/\.(pdf|zip|png|jpe?g|gif|svg|webp|mp4|docx?|xlsx?)$/i', $uri->getPath())) {
                    continue;
                }
                $links[] = (string) $uri;
            } catch (\Throwable) {
                continue;
            }
        }
        $links = array_values(array_unique($links));
        usort($links, fn ($a, $b) => (int) preg_match('/contact|iletisim|iletişim/i', $b) <=> (int) preg_match('/contact|iletisim|iletişim/i', $a));
        return array_slice($links, 0, 20);
    }

    public function contactLinks(string $html, string $url): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $links = [];
        foreach ($dom->getElementsByTagName('a') as $a) {
            $href = $a->getAttribute('href');
            if (! preg_match('/iletisim|iletişim|contact/i', $href.' '.$a->textContent)) {
                continue;
            }
            try {
                $resolved = (string) UriResolver::resolve(new Uri($url), new Uri($href));
            } catch (\Throwable) {
                continue;
            }
            if (parse_url($resolved, PHP_URL_HOST) === parse_url($url, PHP_URL_HOST) && $resolved !== $url) {
                $links[] = $resolved;
            }
        }

        return array_slice(array_unique($links), 0, 1);
    }
}
