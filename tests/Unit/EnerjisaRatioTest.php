<?php

namespace Tests\Unit;

use App\Enerjisa\Services\Reactive\Ratio;
use PHPUnit\Framework\TestCase;

class EnerjisaRatioTest extends TestCase
{
    public function test_exact_threshold_is_not_exceeded_but_a_small_increase_is(): void
    {
        $this->assertFalse(Ratio::between('100', '120', '100', 20)['exceeded']);
        $this->assertTrue(Ratio::between('100', '120.00001', '100', 20)['exceeded']);
        $this->assertFalse(Ratio::between('100', '115', '100', 15)['exceeded']);
        $this->assertTrue(Ratio::between('100', '116', '100', 15)['exceeded']);
    }

    public function test_zero_or_negative_consumption_and_reset_do_not_produce_a_ratio(): void
    {
        $this->assertNull(Ratio::between('100', '120', '0', 20)['percent']);
        $this->assertNull(Ratio::between('100', '120', '-100', 20)['percent']);
        $this->assertNull(Ratio::between('100', '99', '100', 20)['percent']);
    }

    public function test_large_cumulative_indices_keep_small_decimal_differences(): void
    {
        $result = Ratio::between('9999999999999.001', '9999999999999.003', '0.01', 20);
        $this->assertSame('20.000000000', $result['percent']);
        $this->assertFalse($result['exceeded']);
    }
}
