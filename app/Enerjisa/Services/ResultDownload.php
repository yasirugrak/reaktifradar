<?php

namespace App\Enerjisa\Services;

use App\Enerjisa\Models\Query;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultDownload
{
    public function response(Query $query, string $format): StreamedResponse
    {
        abort_unless(in_array($format, ['csv', 'json'], true), 404);
        abort_if($query->error !== null || $query->payload === null, 404);
        $payload = $query->payload;
        $filename = 'enerjisa-sorgu-'.$query->id.'.'.$format;
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private'];

        if ($format === 'json') {
            return response()->streamDownload(function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }, $filename, $headers + ['Content-Type' => 'application/json; charset=UTF-8']);
        }

        // Export the complete snapshot, independently of the displayed page.
        $rows = MdmRecords::rows($payload, $query->kind === 'installations');
        abort_if($rows === [], 404, 'CSV için tablo kaydı yok. Servis yanıtını JSON olarak indirebilirsiniz.');
        $columns = array_values(array_unique(array_merge(...array_map('array_keys', $rows))));

        return response()->streamDownload(function () use ($rows, $columns): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new \RuntimeException('CSV çıktı akışı açılamadı.');
            }
            // UTF-8 BOM and semicolon separator for Turkish Excel installations.
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, array_map(self::cell(...), $columns), ';', '"', '', "\r\n");
            foreach ($rows as $row) {
                fputcsv($stream, array_map(fn ($column): string => self::cell($row[$column] ?? null), $columns), ';', '"', '', "\r\n");
            }
            fclose($stream);
        }, $filename, $headers + ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function cell(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return '';
        }
        $text = (string) $value;
        // Untrusted provider strings must not become spreadsheet formulas.
        // Genuine numeric JSON values (including negative readings) stay numeric.
        if (is_string($value) && preg_match('/^(?:[\x00-\x20]*[=+@-]|[\t\r\n])/', $text)) {
            return "'".$text;
        }

        return $text;
    }
}
