<?php

namespace App\Enerjisa\Services\Reactive;

use App\Enerjisa\Models\Account;
use Carbon\CarbonImmutable;

final class Readings
{
    /** @return array{meters: array<string, list<Reading>>, invalidDates: int, lastQuery: ?string} */
    public function load(Account $account, string $installation, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $indexed = [];
        $invalidDates = 0;
        $lastQuery = null;
        // Only hourly cumulative indices; consumption/production is a different data series.
        foreach ($account->queries()->where('kind', '1')->whereNull('error')->whereNotNull('payload')
            ->where('parameters->installationNumber', $installation)->orderBy('id')->lazy() as $query) {
            $lastQuery = $query->created_at->format('d.m.Y H:i');
            foreach ($this->extract($query->payload ?? [], $installation, $query->id) as $reading) {
                if ($reading === null) {
                    $invalidDates++;

                    continue;
                }
                if ($reading->time->lessThan($start) || $reading->time->greaterThan($end)) {
                    continue;
                }
                $key = $reading->time->format('Y-m-d H:i:s');
                $previous = $indexed[$reading->meter][$key] ?? null;
                if ($previous && $previous->queryId === $reading->queryId &&
                    ($previous->conflict || ! $this->sameIndices($previous, $reading))) {
                    $reading = new Reading($reading->time, $reading->meter, $reading->active, $reading->inductive, $reading->capacitive, $reading->queryId, true);
                }
                // A later query is a newer provider snapshot, not another increment.
                $indexed[$reading->meter][$key] = $reading;
            }
        }
        $meters = [];
        foreach ($indexed as $meter => $records) {
            ksort($records);
            $meters[(string) $meter] = array_values($records);
        }

        return compact('meters', 'invalidDates', 'lastQuery');
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<Reading|null>
     */
    public function extract(array $payload, string $installation, int $queryId): array
    {
        $rows = [];
        $walk = function (array $node, string $meter = 'unknown', ?string $site = null) use (&$walk, &$rows, $installation, $queryId): void {
            foreach ($node as $key => $value) {
                $name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $key) ?? '');
                if (is_scalar($value) && trim((string) $value) !== '') {
                    if (in_array($name, ['meterserialno', 'meterserialnumber', 'meternumber', 'meterserial'], true)) {
                        $meter = trim((string) $value);
                    }
                    if (in_array($name, ['installationnumber', 'instalationnumber'], true)) {
                        $site = trim((string) $value);
                    }
                }
            }
            if ($site !== null && $site !== $installation) {
                return;
            }
            if (array_key_exists('meter_date', $node) || array_key_exists('meterDate', $node)) {
                $time = self::date($node['meter_date'] ?? $node['meterDate'] ?? null);
                $rows[] = $time === null ? null : new Reading(
                    $time, $meter,
                    self::decimal($node['t_top_kWh'] ?? null),
                    self::decimal($node['t_ri_kVarh'] ?? null),
                    self::decimal($node['t_rc_kVarh'] ?? null),
                    $queryId,
                );
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $walk($child, $meter, $site);
                }
            }
        };
        $walk($payload);

        return $rows;
    }

    private function sameIndices(Reading $first, Reading $second): bool
    {
        foreach (['active', 'inductive', 'capacitive'] as $field) {
            $a = $first->$field;
            $b = $second->$field;
            if ($a === null || $b === null) {
                if ($a !== $b) {
                    return false;
                }
            } elseif (bccomp($a, $b, 9) !== 0) {
                return false;
            }
        }

        return true;
    }

    public static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value)) {
            return null;
        }
        foreach (['d/m/Y H:i:s', 'Y-m-d H:i:s', 'Y-m-d\\TH:i:sP'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value, 'Europe/Istanbul');
                if ($date && $date->format($format) === $value) {
                    return $date->setTimezone('Europe/Istanbul');
                }
            } catch (\InvalidArgumentException) {
                // Try the next documented date shape; never silently normalize bad dates.
            }
        }

        return null;
    }

    public static function decimal(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return is_finite($value) ? sprintf('%.9F', $value) : null;
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        // The guide uses 623,858.625 (comma thousands, dot decimals).
        if (preg_match('/^-?\d{1,3}(?:,\d{3})+(?:\.\d{1,9})?$/', $value)) {
            $value = str_replace(',', '', $value);
        } elseif (preg_match('/^-?\d{1,3}(?:\.\d{3})+,\d{1,9}$/', $value)) {
            $value = str_replace(',', '.', str_replace('.', '', $value));
        } elseif (preg_match('/^-?\d+,\d{1,9}$/', $value)) {
            $value = str_replace(',', '.', $value);
        }

        return preg_match('/^-?\d+(?:\.\d{1,9})?$/', $value) ? $value : null;
    }
}
