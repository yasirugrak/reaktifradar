<?php

namespace Tests\Unit;

use App\Enerjisa\Services\Reactive\Reading;
use App\Enerjisa\Services\Reactive\Report;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class EnerjisaReactiveReportTest extends TestCase
{
    private function reading(string $time, ?string $active, ?string $inductive, ?string $capacitive): Reading
    {
        return new Reading(CarbonImmutable::parse($time, 'Europe/Istanbul'), '001', $active, $inductive, $capacitive, 1);
    }

    public function test_hourly_uses_deltas_with_both_thresholds_and_keeps_missing_hours_visible(): void
    {
        $day = CarbonImmutable::parse('2026-08-01', 'Europe/Istanbul');
        $rows = (new Report)->build([
            $this->reading('2026-08-01 00:00', '1000', '500', '400'),
            $this->reading('2026-08-01 01:00', '1100', '521', '416'),
            $this->reading('2026-08-01 03:00', '1200', '540', '430'),
        ], 'hourly', $day, $day, $day->addMonth());
        $this->assertCount(24, $rows);
        $this->assertSame('21.000000000', $rows[0]['inductiveRatio']);
        $this->assertSame('16.000000000', $rows[0]['capacitiveRatio']);
        $this->assertSame('alert', $rows[0]['status']);
        $this->assertSame('missing', $rows[1]['status']);
        $this->assertSame('missing', $rows[2]['status']);
    }

    public function test_daily_uses_midnight_differences_not_average_hourly_ratios(): void
    {
        $day = CarbonImmutable::parse('2026-08-01', 'Europe/Istanbul');
        $rows = (new Report)->build([
            $this->reading('2026-08-01 00:00', '1000', '100', '100'),
            $this->reading('2026-08-01 01:00', '1010', '110', '102'),
            $this->reading('2026-08-02 00:00', '1100', '120', '115'),
        ], 'daily', $day, $day, $day->addMonth());
        $this->assertSame('20.000000000', $rows[0]['inductiveRatio']);
        $this->assertSame('15.000000000', $rows[0]['capacitiveRatio']);
        $this->assertSame('ok', $rows[0]['status']);
    }

    public function test_month_to_date_uses_latest_available_reading_and_marks_partial(): void
    {
        $day = CarbonImmutable::parse('2026-09-01', 'Europe/Istanbul');
        $now = $day->addDays(19)->setHour(13);
        $rows = (new Report)->build([
            $this->reading('2026-09-01 00:00', '1000', '100', '100'),
            $this->reading('2026-09-20 12:00', '2000', '310', '200'),
        ], 'monthly', $day, $now, $now);
        $this->assertTrue($rows[0]['partial']);
        $this->assertSame('21.000000000', $rows[0]['inductiveRatio']);
        $this->assertSame('10.000000000', $rows[0]['capacitiveRatio']);
    }

    public function test_intermediate_reset_invalidates_even_a_positive_endpoint_delta(): void
    {
        $day = CarbonImmutable::parse('2026-08-01', 'Europe/Istanbul');
        $rows = (new Report)->build([
            $this->reading('2026-08-01 00:00', '1000', '100', '100'),
            $this->reading('2026-08-01 12:00', '10', '1', '1'),
            $this->reading('2026-08-02 00:00', '1200', '120', '120'),
        ], 'daily', $day, $day, $day->addMonth());
        $this->assertSame('reset', $rows[0]['status']);
        $this->assertNull($rows[0]['inductiveRatio']);
    }

    public function test_zero_consumption_and_missing_fields_are_not_marked_normal(): void
    {
        $day = CarbonImmutable::parse('2026-08-01', 'Europe/Istanbul');
        $rows = (new Report)->build([
            $this->reading('2026-08-01 00:00', '1000', '100', '100'),
            $this->reading('2026-08-01 01:00', '1000', '110', '110'),
            $this->reading('2026-08-01 02:00', '1100', null, '110'),
        ], 'hourly', $day, $day, $day->addMonth());
        $this->assertSame('zero', $rows[0]['status']);
        $this->assertNull($rows[0]['inductiveRatio']);
        $this->assertSame('missing', $rows[1]['status']);
    }

    public function test_latest_day_uses_last_available_hour_without_waiting_for_midnight(): void
    {
        $day = CarbonImmutable::parse('2026-09-20', 'Europe/Istanbul');
        $readings = [
            $this->reading('2026-09-20 00:00', '100', '10', '10'),
            $this->reading('2026-09-20 13:00', '200', '31', '26'),
            $this->reading('2026-09-20 23:00', '300', '50', '40'),
        ];
        foreach ([$day->setHour(14), $day->addDay()->setHour(10)] as $now) {
            $row = (new Report)->build($readings, 'daily', $day, $day, $now)[0];
            $this->assertTrue($row['partial']);
            $this->assertFalse($row['pending']);
            $this->assertSame($now->isSameDay($day) ? '13:00' : '23:00', $row['last']->time->format('H:i'));
            $this->assertSame($now->isSameDay($day) ? 'alert' : 'ok', $row['status']);
        }
    }

    public function test_partial_fallback_does_not_fill_intermediate_gaps_or_missing_start(): void
    {
        $day = CarbonImmutable::parse('2026-09-19', 'Europe/Istanbul');
        $rows = (new Report)->build([
            $this->reading('2026-09-19 00:00', '100', '10', '10'),
            $this->reading('2026-09-19 23:00', '200', '20', '20'),
            $this->reading('2026-09-20 13:00', '300', '30', '30'),
        ], 'daily', $day, $day->addDay(), $day->addDays(2));
        $this->assertSame('missing', $rows[0]['status']);
        $this->assertFalse($rows[0]['partial']);
        $this->assertSame('missing', $rows[1]['status']);
        $this->assertNull($rows[1]['inductiveRatio']);
    }
}
