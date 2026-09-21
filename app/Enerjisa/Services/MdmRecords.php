<?php

namespace App\Enerjisa\Services;

class MdmRecords
{
    /**
     * Keep original fields: the guide provides no complete response schema.
     *
     * @param  array<array-key, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public static function rows(array $payload, bool $installations = false): array
    {
        $rows = [];
        $walk = function (array $node) use (&$walk, &$rows, $installations): void {
            $record = $installations
                ? (array_key_exists('installationNumber', $node) || array_key_exists('instalationNumber', $node))
                : (array_key_exists('meterDate', $node) || array_key_exists('meter_date', $node));
            if ($record) {
                $rows[] = array_filter($node, fn ($value) => ! is_array($value));
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($payload);

        return $rows;
    }
}
