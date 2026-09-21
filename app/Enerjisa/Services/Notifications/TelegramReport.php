<?php

namespace App\Enerjisa\Services\Notifications;

class TelegramReport
{
    /** @param array<string, mixed>|null $report */
    public function render(string $body, ?array $report): string
    {
        if (! $report || ! isset($report['state']) || $report['state'] === 'legacy') {
            return '<b>⚡ ReaktifRadar</b>'."\n\n".$this->escape($body, 3500);
        }
        $status = match ($report['state']) {
            'healthy' => '✅ Oranlar sınırlar içinde',
            'alert' => '🔴 Eşik aşımı tespit edildi',
            'unavailable' => '⚠️ Güncel veriler alınamadı',
            default => '⚠️ Veriler eksik veya hesaplanamıyor',
        };
        $text = '<b>⚡ ReaktifRadar</b>'."\n".(($report['frequency'] ?? '') === 'weekly' ? 'Haftalık' : 'Günlük')." enerji raporu\n\n"
            .'<b>'.$status.'</b>'."\n\n"
            .'<b>Tesisat:</b> <code>'.$this->escape($report['installation'] ?? '', 60)."</code>\n";
        if (! empty($report['owner'])) {
            $text .= $this->escape($report['owner'], 200)."\n";
        }
        $text .= '<b>Dönem:</b> '.$this->escape($report['period'] ?? '', 60)." (İstanbul)\n";
        if ($report['state'] === 'unavailable') {
            return $text."\nGüncel ölçümlere ulaşılamadı; sorun olmadığı doğrulanamadı. Enerjisa bağlantısını ve sorgu geçmişini kontrol edin.";
        }
        $text .= "\n<b>Özet</b>\n".(int) ($report['total'] ?? 0).' sayaç/gün incelendi · '.(int) ($report['alerts'] ?? 0)." eşik aşımı\n";
        if (! empty($report['partial'])) {
            $text .= "⏳ Kısmi gün: hesaplama son ölçüme kadardır.\n";
        }
        if (! empty($report['invalid'])) {
            $text .= "⚠️ Eksik/geçersiz ölçümler var; panelden kontrol edin.\n";
        }
        $rows = $report['rows'] ?? [];
        foreach (array_slice($rows, 0, 6) as $row) {
            $text .= "\n<b>".$this->escape($row['day'], 40).'</b> · Sayaç '.$this->escape($row['meter'], 40)."\n";
            if (! in_array($row['status'], ['ok', 'alert'], true)) {
                $text .= '⚠️ '.$this->escape($row['message'] ?: 'Hesaplanamadı.', 100)."\n";
            } else {
                $text .= $this->ratio('Endüktif', $row['inductive'], $row['inductiveAlert'])."\n"
                    .$this->ratio('Kapasitif', $row['capacitive'], $row['capacitiveAlert'])."\n";
            }
            if (! empty($row['lastTime'])) {
                $text .= 'Son ölçüm: '.$this->escape($row['lastTime'], 30).(! empty($row['partial']) ? ' · Kısmi gün' : '')."\n";
            }
        }
        if (($report['total'] ?? count($rows)) > 6) {
            $text .= "\nDiğer sonuçlar panelde.\n";
        }

        return $text."\n<i>Eşikler: endüktif &gt; %20 · kapasitif &gt; %15\nHesaplama günlük endeks farklarına dayanır.</i>";
    }

    private function ratio(string $label, mixed $value, bool $alert): string
    {
        $formatted = is_numeric($value) ? '%'.number_format((float) $value, 3, ',', '') : 'Hesaplanamadı';

        return ($alert ? '🔴 ' : '• ').$label.': <b>'.$formatted.'</b>'.($alert ? ' — Sınır aşıldı' : '');
    }

    private function escape(string $value, int $limit): string
    {
        return htmlspecialchars(mb_strimwidth($value, 0, $limit, '…'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
