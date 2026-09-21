<?php

namespace App\Enerjisa\Services\Reactive;

use Carbon\CarbonImmutable;

final class Report
{
    /**
     * @param  list<Reading>  $readings
     * @return list<array<string, mixed>>
     */
    public function build(array $readings, string $period, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now): array
    {
        $results = [];
        foreach ((new Periods)->build($readings, $period, $from, $to, $now) as $range) {
            $row = $range + [
                'status' => 'missing', 'message' => 'Ölçüm eksik',
                'activeDelta' => null, 'inductiveDelta' => null, 'capacitiveDelta' => null,
                'inductiveRatio' => null, 'capacitiveRatio' => null,
                'inductiveAlert' => false, 'capacitiveAlert' => false,
            ];
            $first = $range['first'];
            $last = $range['last'];
            if ($range['pending']) {
                $row['status'] = 'pending';
                $row['message'] = 'Dönem tamamlanmadı';
            } elseif ($first && $last && $last->time->greaterThan($first->time)) {
                $problem = $this->problem($readings, $first, $last);
                if ($problem !== null) {
                    $row['status'] = $problem;
                    $row['message'] = match ($problem) {
                        'reset' => 'Endeks azalmış / sayaç reseti',
                        'conflict' => 'Çelişen ölçüm',
                        default => 'Endeks alanı eksik',
                    };
                } else {
                    // problem() has checked both endpoints. Decimal strings avoid
                    // loss of precision on large cumulative meter counters.
                    $active = bcsub((string) $last->active, (string) $first->active, 9);
                    $inductive = Ratio::between((string) $first->inductive, (string) $last->inductive, $active, 20);
                    $capacitive = Ratio::between((string) $first->capacitive, (string) $last->capacitive, $active, 15);
                    $row['activeDelta'] = $active;
                    $row['inductiveDelta'] = $inductive['delta'];
                    $row['capacitiveDelta'] = $capacitive['delta'];
                    $row['inductiveRatio'] = $inductive['percent'];
                    $row['capacitiveRatio'] = $capacitive['percent'];
                    $row['inductiveAlert'] = $inductive['exceeded'];
                    $row['capacitiveAlert'] = $capacitive['exceeded'];
                    $row['status'] = bccomp($active, '0', 9) === 0 ? 'zero'
                        : ($inductive['exceeded'] || $capacitive['exceeded'] ? 'alert' : 'ok');
                    $row['message'] = match ($row['status']) {
                        'zero' => 'Aktif tüketim sıfır', 'alert' => 'Eşik aşımı', default => 'Eşikler içinde',
                    };
                }
            }
            $results[] = $row;
        }

        return $results;
    }

    /** @param list<Reading> $readings */
    private function problem(array $readings, Reading $first, Reading $last): ?string
    {
        foreach ([$first, $last] as $reading) {
            if ($reading->active === null || $reading->inductive === null || $reading->capacitive === null) {
                return 'missing';
            }
        }
        $previous = [];
        foreach ($readings as $reading) {
            if ($reading->time->lessThan($first->time) || $reading->time->greaterThan($last->time)) {
                continue;
            }
            if ($reading->conflict) {
                return 'conflict';
            }
            foreach (['active', 'inductive', 'capacitive'] as $field) {
                $value = $reading->$field;
                if ($value === null) {
                    continue;
                }
                if (bccomp($value, '0', 9) < 0 || (isset($previous[$field]) && bccomp($value, $previous[$field], 9) < 0)) {
                    return 'reset';
                }
                $previous[$field] = $value;
            }
        }

        return null;
    }
}
