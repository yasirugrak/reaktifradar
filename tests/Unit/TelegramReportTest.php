<?php

namespace Tests\Unit;

use App\Enerjisa\Services\Notifications\TelegramReport;
use PHPUnit\Framework\TestCase;

class TelegramReportTest extends TestCase
{
    public function test_dynamic_text_is_escaped_without_cutting_entities(): void
    {
        $text = (new TelegramReport)->render(str_repeat('<>&😀', 2000), null);
        $this->assertStringNotContainsString('<>&', $text);
        $this->assertStringContainsString('&lt;&gt;&amp;', $text);
        $this->assertLessThan(4096, mb_strlen(html_entity_decode(strip_tags($text))));
    }

    public function test_alert_report_preserves_ratios_and_partial_data_warning(): void
    {
        $row = ['day' => '21.09.2026', 'meter' => '123', 'status' => 'alert', 'inductive' => 21.5,
            'capacitive' => 12.2, 'inductiveAlert' => true, 'capacitiveAlert' => false,
            'lastTime' => '21.09.2026 12:00', 'partial' => true];
        $text = (new TelegramReport)->render('', ['state' => 'alert', 'owner' => '<Firma & Ortak>',
            'total' => 12, 'alerts' => 12, 'partial' => 1, 'invalid' => 1, 'rows' => array_fill(0, 12, $row)]);
        $this->assertStringContainsString('&lt;Firma &amp; Ortak&gt;', $text);
        $this->assertStringContainsString('🔴 Endüktif: <b>%21,500</b> — Sınır aşıldı', $text);
        $this->assertStringContainsString('• Kapasitif: <b>%12,200</b>', $text);
        $this->assertStringContainsString('Kısmi gün', $text);
        $this->assertStringContainsString('Eksik/geçersiz', $text);
        $this->assertStringContainsString('Diğer sonuçlar panelde.', $text);
        $this->assertSame(6, substr_count($text, 'Sayaç 123'));
        $this->assertLessThan(4096, mb_strlen(html_entity_decode(strip_tags($text))));
    }
}
