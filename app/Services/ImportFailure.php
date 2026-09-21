<?php

namespace App\Services;

use Illuminate\Database\QueryException;

final class ImportFailure
{
    /** @return array{connection: string, state: string, reason: string} */
    public static function describe(QueryException $exception): array
    {
        $state = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $state = preg_match('/^[A-Z0-9]{5}$/', $state) ? $state : 'bilinmiyor';
        // Inspect internally; never expose raw driver text, SQL or bindings.
        $message = strtolower($exception->getPrevious()?->getMessage() ?? '');
        $reason = match (true) {
            str_contains($message, 'password authentication failed') => 'Veritabanı kullanıcı adı veya parolası kabul edilmedi.',
            str_contains($message, 'no password supplied') => 'Veritabanı parolası yapılandırılmamış.',
            str_contains($message, 'could not translate host name'), str_contains($message, 'name or service not known') => 'Veritabanı sunucusunun adı çözülemiyor; Docker ağını kontrol edin.',
            str_contains($message, 'connection refused'), str_contains($message, 'timeout expired') => 'Veritabanı sunucusuna ulaşılamıyor; servis ve ağ bağlantısını kontrol edin.',
            $state === '3D000', (str_contains($message, 'database') && str_contains($message, 'does not exist')) => 'Bağlanılacak database bulunamadı.',
            $state === '42P01' => 'Gerekli tablo bulunamadı; ilgili veritabanının migration durumunu kontrol edin.',
            $state === '42703' => 'Gerekli kolon bulunamadı; kaynak ve hedef şemaları güncel değil.',
            $state === '42501' => 'Veritabanı kullanıcısının gerekli tablo veya şema yetkisi yok.',
            $state === '23505' => 'Hedefte aynı kimlik veya benzersiz alanla bir kayıt var.',
            $state === '23503' => 'Kayıtlar arasındaki ilişki doğrulanamadı.',
            default => 'Bağlantı ayarlarını, migration durumunu ve veritabanı yetkilerini kontrol edin.',
        };

        return ['connection' => $exception->getConnectionName() === 'legacy' ? 'Kaynak (OkuKid / LEGACY_DB_*)' : 'Hedef (ReaktifRadar / DB_*)', 'state' => $state, 'reason' => $reason];
    }
}
