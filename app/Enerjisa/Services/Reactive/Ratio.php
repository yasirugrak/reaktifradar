<?php

namespace App\Enerjisa\Services\Reactive;

final class Ratio
{
    /** @return array{delta: string, percent: ?string, exceeded: bool} */
    public static function between(string $first, string $last, string $activeDelta, int $threshold): array
    {
        $delta = bcsub($last, $first, 9);
        $canDivide = bccomp($activeDelta, '0', 9) > 0 && bccomp($delta, '0', 9) >= 0;
        $numerator = bcmul($delta, '100', 9);

        return [
            'delta' => $delta,
            'percent' => $canDivide ? bcdiv($numerator, $activeDelta, 9) : null,
            // Compare before formatting/rounding to keep equality below the alert boundary.
            'exceeded' => $canDivide && bccomp($numerator, bcmul($activeDelta, (string) $threshold, 9), 9) > 0,
        ];
    }
}
