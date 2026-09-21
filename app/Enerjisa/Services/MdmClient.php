<?php

namespace App\Enerjisa\Services;

use App\Enerjisa\Models\Account;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MdmClient
{
    // The guide mixes regional examples. Never send a Başkent credential to AYEDAŞ.
    private const BASE = 'https://mdmsaatlik.baskentedas.com.tr/baskent/mdm-api/';

    private const ERRORS = [
        1 => 'Bu tesisatı sorgulamak için Enerjisa yetkiniz bulunmuyor.',
        2 => 'Sorgu tarih aralığı servis sınırlarının dışında.',
        3 => 'Enerjisa zorunlu bir parametrenin eksik olduğunu bildirdi.',
        4 => 'Sorgu tarih aralığı servis sınırlarının dışında.',
        5 => 'Bugünün verisi sorgulanamaz. En geç dünü seçin.',
        6 => 'Enerjisa kullanıcı tipiniz bu işlem için yetkili değil.',
    ];

    private function http(): PendingRequest
    {
        return Http::acceptJson()->connectTimeout(10)->timeout(45)->withoutRedirecting()
            ->withHeaders(['consumerID' => 'MDM']);
    }

    public function token(Account $account): string
    {
        if (! $account->client_id || ! $account->client_secret) {
            throw new MdmException('Önce Enerjisa erişim bilgilerinizi kaydedin.');
        }
        try {
            $response = $this->http()->withQueryParameters([
                'grant_type' => 'client_credentials',
                'client_id' => $account->client_id,
                'client_secret' => $account->client_secret,
                'consumerID' => 'MDMAYPRD',
            ])->post(self::BASE.'oauth/token');
        } catch (ConnectionException $e) {
            throw $this->failure('Enerjisa servisine ulaşılamadı. Lütfen tekrar deneyin.', 'oauth/token', 'POST', $e);
        }
        if (! $response->successful()) {
            throw $this->failure('Enerjisa oturumu açılamadı. Erişim bilgilerinizi ve servis yetkinizi kontrol edin.', 'oauth/token', 'POST', response: $response);
        }
        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw $this->failure('Enerjisa geçerli bir erişim jetonu döndürmedi.', 'oauth/token', 'POST', response: $response);
        }

        return $token;
    }

    /**
     * @param  array<string, string|int>  $parameters
     * @return array<array-key, mixed>
     */
    public function fetch(Account $account, string $kind, array $parameters = []): array
    {
        $endpoint = match ($kind) {
            'installations' => 'installation-list',
            'hourly' => 'hourly-meter-information-multi-installation',
            '1', '2', '3' => 'energy-value',
            default => throw new MdmException('Geçersiz sorgu türü.'),
        };
        foreach ([0, 1] as $attempt) {
            $token = $this->token($account);
            try {
                $response = $this->http()->get(self::BASE.'customer/'.$endpoint, array_merge($parameters, [
                    'access_token' => $token, 'consumerID' => 'MDMAYPRD',
                ]));
            } catch (ConnectionException $e) {
                throw $this->failure('Enerjisa yanıt vermedi. Bir süre sonra tekrar deneyin.', 'customer/'.$endpoint, 'GET', $e);
            }
            if ($response->status() !== 401 || $attempt === 1) {
                break;
            }
        }
        if (! $response->successful()) {
            throw $this->failure('Enerjisa sorgusu tamamlanamadı (HTTP '.$response->status().').', 'customer/'.$endpoint, 'GET', response: $response);
        }
        $data = $response->json();
        if (! is_array($data)) {
            $description = match ($this->responseKind($response)) {
                'empty' => 'Enerjisa boş yanıt döndürdü.',
                'html' => 'Enerjisa veri yerine HTML sayfası döndürdü.',
                'invalid_json' => 'Enerjisa geçersiz JSON veya metin yanıtı döndürdü.',
                default => 'Enerjisa beklenen JSON nesnesi veya listesini döndürmedi.',
            };
            throw $this->failure($description.' (HTTP '.$response->status().').', 'customer/'.$endpoint, 'GET', response: $response);
        }
        $this->checkStatus($data);

        return $this->redact($data, [$token, $account->client_secret, $account->client_id]);
    }

    private function failure(string $message, string $endpoint, string $method, ?ConnectionException $exception = null, ?Response $response = null): MdmException
    {
        if (! config('enerjisa.debug')) {
            return new MdmException($message);
        }
        $context = [];
        for ($previous = $exception?->getPrevious(); $previous !== null; $previous = $previous->getPrevious()) {
            if ($previous instanceof ConnectException) {
                $context = $previous->getHandlerContext();
                break;
            }
        }
        $errno = (int) ($context['errno'] ?? 0);
        if ($errno === 0 && $exception && preg_match('/cURL error (\d+)/', $exception->getMessage(), $match)) {
            $errno = (int) $match[1];
        }
        $details = [
            'reference' => (string) Str::uuid(),
            'time' => now()->toIso8601String(),
            'method' => $method,
            'endpoint' => self::BASE.$endpoint,
            'category' => $response ? ($response->successful() ? 'Beklenmeyen yanıt biçimi' : 'HTTP hatası') : match ($errno) {
                5, 6 => 'DNS / adres çözümleme',
                7 => 'TCP bağlantısı kurulamadı',
                28 => 'Zaman aşımı',
                35 => 'TLS el sıkışması',
                51, 60 => 'TLS sertifika doğrulaması',
                52 => 'Sunucu boş yanıt verdi',
                55, 56 => 'Ağda veri gönderme / alma hatası',
                default => 'Ağ bağlantısı hatası',
            },
            'connect_timeout_seconds' => 10,
            'timeout_seconds' => 45,
        ];
        if ($exception) {
            $details['curl_errno'] = $errno;
        }
        if ($response) {
            $details['http_status'] = $response->status();
            $details['response_bytes'] = strlen($response->body());
            $details['response_kind'] = $this->responseKind($response);
            $mediaType = strtolower(trim(explode(';', $response->header('Content-Type'))[0]));
            $details['content_type'] = in_array($mediaType, ['application/json', 'text/json', 'text/html', 'text/plain', 'application/xml', 'text/xml'], true)
                ? $mediaType : ($mediaType === '' ? 'missing' : 'other');
            json_decode($response->body(), true);
            $details['json_error_code'] = json_last_error();
            $context = $response->handlerStats();
        }
        // Strict numeric allowlist. Never log exception text, context.error, URL,
        // response bodies, redirect targets or arbitrary provider headers.
        foreach (['namelookup_time', 'connect_time', 'appconnect_time', 'starttransfer_time', 'total_time', 'ssl_verify_result'] as $key) {
            if (isset($context[$key]) && is_numeric($context[$key])) {
                $details[$key] = (float) $context[$key];
            }
        }
        Log::warning('Enerjisa MDM diagnostic', $details);

        return new MdmException($message, $details);
    }

    private function responseKind(Response $response): string
    {
        $body = trim($response->body());
        if ($body === '') {
            return 'empty';
        }
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return is_array($decoded) ? 'json_collection' : 'json_scalar';
        }
        if (preg_match('/^(?:\xEF\xBB\xBF)?\s*<(?:!doctype\s+html|html|head|body)\b/i', $body)) {
            return 'html';
        }

        return 'invalid_json';
    }

    /** @param array<array-key, mixed> $data */
    private function checkStatus(array $data): void
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), ['status', 'message_type', 'messagetype'], true) && is_numeric($value) && (int) $value !== 0) {
                throw new MdmException(self::ERRORS[(int) $value] ?? 'Enerjisa bilinmeyen bir servis hata kodu döndürdü.', serviceCode: (int) $value);
            }
            if (is_array($value)) {
                $this->checkStatus($value);
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $secrets
     * @return array<array-key, mixed>
     */
    private function redact(array $data, array $secrets): array
    {
        foreach ($data as $key => $value) {
            if (preg_match('/token|secret|password|client_id|authorization/i', (string) $key)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value, $secrets);
            } elseif (is_string($value)) {
                $data[$key] = str_replace(array_filter($secrets), '[gizli]', $value);
            }
        }

        return $data;
    }
}
