<?php

namespace Tests\Unit;

use App\Enerjisa\Services\Reactive\Periods;
use App\Enerjisa\Services\Reactive\Reading;
use App\Enerjisa\Services\Reactive\Readings;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class EnerjisaReadingsTest extends TestCase
{
    public function test_document_number_formats_and_missing_values(): void
    {
        $this->assertSame('623858.625', Readings::decimal('623,858.625'));
        $this->assertSame('623858.625', Readings::decimal('623.858,625'));
        $this->assertSame('0.25', Readings::decimal('0,25'));
        $this->assertSame('0', Readings::decimal(0));
        $this->assertNull(Readings::decimal(''));
        $this->assertNull(Readings::decimal('unknown'));
    }

    public function test_readings_keep_parent_meter_identity_and_exclude_other_installations(): void
    {
        $records = (new Readings)->extract(['data' => [
            ['installationNumber' => '123', 'meter_serial_no' => '001', 'values' => [
                ['meter_date' => '01/08/2026 00:00:00', 't_top_kWh' => '1,200.5', 't_ri_kVarh' => '20', 't_rc_kVarh' => '10'],
            ]],
            ['installationNumber' => 'other', 'values' => [['meterDate' => '2026-08-01 00:00:00']]],
        ]], '123', 1);
        $this->assertCount(1, $records);
        $this->assertSame('001', $records[0]->meter);
        $this->assertSame('1200.5', $records[0]->active);
        $this->assertSame('2026-08-01 00:00:00+03:00', $records[0]->time->format('Y-m-d H:i:sP'));
        $this->assertNull(Readings::date('31/02/2026 00:00:00'));
    }

    private function reading(string $time): Reading
    {
        return new Reading(CarbonImmutable::parse($time, 'Europe/Istanbul'), '001', '100', '20', '10', 1);
    }

    public function test_daily_requires_exact_midnight_and_includes_next_days_boundary(): void
    {
        $from = CarbonImmutable::parse('2026-08-01', 'Europe/Istanbul');
        $rows = (new Periods)->build([
            $this->reading('2026-08-01 00:05:00'), $this->reading('2026-08-02 00:00:00'),
        ], 'daily', $from, $from, $from->addMonth());
        $this->assertNull($rows[0]['first']);
        $this->assertSame('2026-08-02 00:00', $rows[0]['last']->time->format('Y-m-d H:i'));
    }

    public function test_month_uses_last_reading_in_month_not_next_months_midnight(): void
    {
        $from = CarbonImmutable::parse('2026-08-01', 'Europe/Istanbul');
        $rows = (new Periods)->build([
            $this->reading('2026-08-01 00:00:00'), $this->reading('2026-08-31 23:00:00'), $this->reading('2026-09-01 00:00:00'),
        ], 'monthly', $from->addDays(3), $from->addDays(5), $from->addMonths(2));
        $this->assertSame('2026-08-01 00:00', $rows[0]['first']->time->format('Y-m-d H:i'));
        $this->assertSame('2026-08-31 23:00', $rows[0]['last']->time->format('Y-m-d H:i'));
        $this->assertFalse($rows[0]['partial']);
    }
}
