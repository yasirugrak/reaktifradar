<?php

namespace App\Enerjisa\Services\Notifications;

use App\Enerjisa\Models\NotificationRule;
use App\Enerjisa\Services\MdmRecords;
use App\Enerjisa\Services\Reactive\Readings;
use App\Enerjisa\Services\Reactive\Report;
use App\Enerjisa\Services\Reactive\Sync;
use Carbon\CarbonImmutable;
use Throwable;

class Summary
{
    /** @return array{healthy: bool, body: string, email: array<string, mixed>} */
    public function build(NotificationRule $rule, CarbonImmutable $date): array
    {
        $end = $date->startOfDay();
        $start = $rule->frequency === 'weekly' ? $end->subDays(6) : $end;
        $account = $rule->account;
        $snapshot = $account->queries()->where('kind', 'installations')->whereNull('error')->latest('id')->first();
        $owner = '';
        foreach (MdmRecords::rows($snapshot->payload ?? [], true) as $row) {
            if ((string) ($row['installationNumber'] ?? $row['instalationNumber'] ?? '') === $rule->installation) {
                $owner = (string) ($row['customerName'] ?? '');
            }
        }
        $email = ['owner' => $owner, 'installation' => $rule->installation, 'frequency' => $rule->frequency,
            'start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'),
            'period' => $start->format('d.m.Y').' – '.$end->format('d.m.Y')];
        $header = 'Enerji durum raporu'."\nTesisat: ".$rule->installation.($owner !== '' ? "\n".mb_substr($owner, 0, 200) : '')
            ."\nDönem: ".$start->format('d.m.Y').' – '.$end->format('d.m.Y')." (İstanbul)\n";
        try {
            app(Sync::class)->run($account, ['installation' => $rule->installation, 'period' => 'daily',
                'start' => $start->subDays(2)->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'refresh' => true]);
            $loaded = app(Readings::class)->load($account, $rule->installation, $start->subDays(2), $date);
            $latest = null;
            foreach ($loaded['meters'] as $series) {
                foreach ($series as $reading) {
                    if ($latest === null || $reading->time->greaterThan($latest)) {
                        $latest = $reading->time;
                    }
                }
            }
            if ($latest !== null) {
                // A midnight reading closes the preceding day; it alone does not open a measurable new day.
                $end = ($latest->equalTo($latest->startOfDay()) ? $latest->subSecond() : $latest)->startOfDay();
                $start = $rule->frequency === 'weekly' ? $end->subDays(6) : $end;
                $email['start'] = $start->format('Y-m-d');
                $email['end'] = $end->format('Y-m-d');
                $email['period'] = $start->format('d.m.Y').' – '.$end->format('d.m.Y');
                $header = 'Enerji durum raporu'."\nTesisat: ".$rule->installation.($owner !== '' ? "\n".mb_substr($owner, 0, 200) : '')."\nDönem: ".$email['period']." (İstanbul)\n";
            }
            $alerts = 0;
            $invalid = $loaded['invalidDates'] > 0 ? 1 : 0;
            $total = 0;
            $partial = 0;
            $details = [];
            $emailRows = [];
            foreach ($loaded['meters'] as $meter => $readings) {
                foreach (app(Report::class)->build($readings, 'daily', $start, $end, $date) as $row) {
                    $total++;
                    if ($row['partial']) {
                        $partial++;
                    }
                    $emailRows[] = ['day' => $row['label'], 'meter' => (string) $meter, 'status' => $row['status'],
                        'partial' => $row['partial'], 'lastTime' => $row['last']?->time->format('d.m.Y H:i'),
                        'message' => $row['message'], 'inductive' => $row['inductiveRatio'], 'capacitive' => $row['capacitiveRatio'],
                        'inductiveAlert' => $row['inductiveAlert'], 'capacitiveAlert' => $row['capacitiveAlert']];
                    if ($row['status'] === 'alert') {
                        $alerts++;
                        if (count($details) < 8) {
                            $details[] = $row['label'].' / Sayaç '.$meter.': Endüktif %'.number_format((float) $row['inductiveRatio'], 3, ',', '')
                                .', kapasitif %'.number_format((float) $row['capacitiveRatio'], 3, ',', '');
                        }
                    } elseif ($row['status'] !== 'ok') {
                        $invalid++;
                    }
                }
            }
            $healthy = $total > 0 && $alerts === 0 && $invalid === 0;
            $status = $healthy ? 'Gelen ölçümlere göre sorun yok. İncelenen oranlar belirlenen eşikler içinde.'
                : ($alerts > 0 ? 'Dikkat: '.$alerts.' sayaç/gün için eşik aşımı var.' : 'Veriler eksik veya hesaplanamıyor; sorun olmadığı doğrulanamadı.');
            if ($partial > 0) {
                $status .= "\nKısmi gün: son ölçüme kadar hesaplandı; günün tamamını kapsamaz.";
            }
            if ($invalid > 0) {
                $status .= "\nEksik/geçersiz ölçüm var. Paneldeki hesaplanamayan dönemleri kontrol edin.";
            }
            if ($latest !== null) {
                $status .= "\nSon ölçüm: ".$latest->format('d.m.Y H:i').' (İstanbul)';
            }
            $body = $header.$status."\n".implode("\n", $details)
                ."\nEşikler: endüktif > %20, kapasitif > %15.\nGünlük endeks farkları esas alınır. Son gün, alınabilen son ölçüme kadar hesaplanır.";

            usort($emailRows, fn ($a, $b) => ($a['status'] === 'ok' ? 1 : 0) <=> ($b['status'] === 'ok' ? 1 : 0));
            $email += ['state' => $healthy ? 'healthy' : ($alerts > 0 ? 'alert' : 'incomplete'),
                'partial' => $partial, 'total' => $total, 'alerts' => $alerts, 'invalid' => $invalid, 'rows' => array_slice($emailRows, 0, 12)];

            return ['healthy' => $healthy, 'body' => $body, 'email' => $email];
        } catch (Throwable) {
            return ['healthy' => false, 'email' => $email + ['state' => 'unavailable'], 'body' => $header.'Güncel veriler alınamadı; sorun olmadığı doğrulanamadı. Enerjisa bağlantısını ve sorgu geçmişini kontrol edin.'];
        }
    }
}
