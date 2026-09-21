<?php

namespace App\Enerjisa\Services\Reactive;

use Carbon\CarbonImmutable;

final class Periods
{
    /**
     * @param  list<Reading>  $readings
     * @return list<array{label: string, start: CarbonImmutable, end: CarbonImmutable, first: ?Reading, last: ?Reading, partial: bool, pending: bool}>
     */
    public function build(array $readings, string $period, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now): array
    {
        $byTime = [];
        $latest = null;
        foreach ($readings as $reading) {
            if ($reading->time->greaterThan($now)) {
                continue;
            }
            if ($latest === null || $reading->time->greaterThan($latest->time)) {
                $latest = $reading;
            }
            $byTime[$reading->time->format('Y-m-d H:i:s')] = $reading;
        }
        $cursor = $period === 'monthly' ? $from->startOfMonth() : $from->startOfDay();
        $limit = $period === 'monthly' ? $to->endOfMonth() : $to->endOfDay();
        $rows = [];
        while ($cursor->lessThanOrEqualTo($limit) && $cursor->lessThanOrEqualTo($now)) {
            $end = match ($period) {
                'hourly' => $cursor->addHour(),
                'daily' => $cursor->addDay(),
                default => $cursor->addMonth(),
            };
            $first = $byTime[$cursor->format('Y-m-d H:i:s')] ?? null;
            $last = $byTime[$end->format('Y-m-d H:i:s')] ?? null;
            $partial = false;
            $pending = $end->greaterThan($now);
            if ($period === 'daily' && $last === null && $latest !== null && $latest->time->isSameDay($cursor) && $latest->time->greaterThan($cursor)) {
                $last = $latest;
                $partial = true;
                $pending = false;
            }
            if ($period === 'monthly') {
                $last = null;
                foreach ($readings as $reading) {
                    if ($reading->time->greaterThan($cursor) && $reading->time->lessThan($end) && $reading->time->lessThanOrEqualTo($now)) {
                        $last = $reading;
                    }
                }
                $partial = $pending || $last === null || $last->time->lessThan($end->subHour());
                $pending = false; // Month-to-date is a valid partial calculation.
            }
            $rows[] = [
                'label' => match ($period) {
                    'hourly' => $cursor->format('d.m.Y H:i').' – '.$end->format('H:i'),
                    'daily' => $cursor->format('d.m.Y'),
                    default => $cursor->format('m.Y'),
                },
                'start' => $cursor, 'end' => $end, 'first' => $first, 'last' => $last,
                'partial' => $partial, 'pending' => $pending,
            ];
            $cursor = $end;
        }

        return $rows;
    }
}
