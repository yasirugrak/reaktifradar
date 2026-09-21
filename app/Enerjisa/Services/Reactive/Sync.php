<?php

namespace App\Enerjisa\Services\Reactive;

use App\Enerjisa\Models\Account;
use App\Enerjisa\Services\MdmClient;
use App\Enerjisa\Services\MdmException;
use Carbon\CarbonImmutable;

final class Sync
{
    public function __construct(private MdmClient $client) {}

    /** @param array<string, mixed> $filters */
    public function run(Account $account, array $filters): int
    {
        $from = CarbonImmutable::parse($filters['start'], 'Europe/Istanbul')->startOfDay();
        $to = CarbonImmutable::parse($filters['end'], 'Europe/Istanbul')->startOfDay();
        if ($filters['period'] === 'monthly') {
            $from = $from->startOfMonth();
            $to = $to->endOfMonth()->startOfDay();
        } else {
            // The final interval ends at midnight on the following day.
            $to = $to->addDay();
        }
        $now = CarbonImmutable::now('Europe/Istanbul');
        $today = $now->startOfDay();
        $probeToday = $to->greaterThanOrEqualTo($today) && $from->lessThanOrEqualTo($today);
        $to = $to->min($today->subDay());
        $covered = [];
        if (empty($filters['refresh'])) {
            foreach ($account->queries()->where('kind', '1')->whereNull('error')->whereNotNull('payload')
                ->where('parameters->installationNumber', $filters['installation'])->lazy() as $query) {
                $start = Readings::date($query->parameters['startDate'] ?? null);
                $end = Readings::date($query->parameters['endDate'] ?? null);
                if (! $start || ! $end) {
                    continue;
                }
                for ($day = $start->startOfDay()->max($from); $day->lessThanOrEqualTo($end->min($to)); $day = $day->addDay()) {
                    // Only full days are considered covered by a previous query.
                    if ($start->lessThanOrEqualTo($day) && $end->greaterThanOrEqualTo($day->endOfDay()->setMicrosecond(0))) {
                        $covered[$day->format('Y-m-d')] = true;
                    }
                }
            }
        }
        $count = 0;
        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $end->addDay()) {
            $end = $day;
            if (isset($covered[$day->format('Y-m-d')])) {
                continue;
            }
            $limit = $day->endOfMonth()->startOfDay()->min($to);
            while ($end->lessThan($limit) && ! isset($covered[$end->addDay()->format('Y-m-d')])) {
                $end = $end->addDay();
            }
            $parameters = [
                'installationNumber' => $filters['installation'], 'dataType' => 1,
                'startDate' => $day->format('d/m/Y').' 00:00:00',
                'endDate' => $end->format('d/m/Y').' 23:59:59',
            ];
            $this->fetch($account, $parameters);
            $count++;
        }

        if ($probeToday) {
            try {
                // Isolate today's request: a provider restriction cannot discard historical results.
                $this->fetch($account, ['installationNumber' => $filters['installation'], 'dataType' => 1,
                    'startDate' => $today->format('d/m/Y H:i:s'), 'endDate' => $now->format('d/m/Y H:i:s')]);
                $count++;
            } catch (MdmException $exception) {
                if ($exception->serviceCode !== 5) {
                    throw $exception;
                }
            }
        }

        return $count;
    }

    /** @param array<string, string|int> $parameters */
    private function fetch(Account $account, array $parameters): void
    {
        $query = $account->queries()->create(['kind' => '1', 'parameters' => $parameters]);
        try {
            $query->update(['payload' => $this->client->fetch($account, '1', $parameters)]);
        } catch (MdmException $exception) {
            $query->update(['error' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
