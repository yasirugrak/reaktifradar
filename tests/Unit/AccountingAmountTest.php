<?php
namespace Tests\Unit;
use App\Accounting\Controller;
use PHPUnit\Framework\TestCase;
class AccountingAmountTest extends TestCase {
 public function test_amounts_preserve_four_decimal_places_without_float_rounding(): void {
 $this->assertSame(1001,Controller::units('0.1001'));
 $this->assertSame(1234500,Controller::units('123.45'));
 $this->assertSame(99999999999999,Controller::units('9999999999.9999'));
 }
}
