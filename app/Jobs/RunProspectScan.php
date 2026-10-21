<?php

namespace App\Jobs;

use App\Models\ProspectContact;
use App\Exceptions\ProspectSearchException;
use App\Models\ProspectScan;
use App\Services\ProspectCrawler;
use App\Services\ProspectSearch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunProspectScan implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $scanId)
    {
        $this->onConnection('database');
    }

    public function handle(ProspectSearch $search, ProspectCrawler $crawler): void
    {
        $scan = ProspectScan::find($this->scanId);
        if (! $scan || in_array($scan->status, ['cancelled', 'completed', 'failed'])) {
            return;
        }
        $direct = $scan->provider === 'website';
        if ($direct) {
            $scan->update(['status' => 'running']);
        }
        if ($scan->candidates === null) {
            $scan->update(['status' => 'running']);
            $candidates = $search->discover($scan);
            if (in_array($scan->fresh()?->status, [null, 'cancelled'], true)) {
                return;
            }
            $scan->update(['candidates' => $candidates]);
        } else {
            $candidate = $scan->candidates[$scan->processed] ?? null;
            if ($candidate) {
                try {
                    $html = $crawler->fetch($candidate['url']);
                    $pages = [$candidate['url'] => $html];
                    if ($direct) {
                        $candidates = $scan->candidates;
                        $known = array_column($candidates, 'url');
                        foreach ($crawler->siteLinks($html, $candidate['url']) as $link) {
                            if (count($candidates) >= 20) {
                                break;
                            }
                            if (! in_array($link, $known, true)) {
                                $candidates[] = ['url' => $link, 'company' => $candidate['company']];
                                $known[] = $link;
                            }
                        }
                        $scan->update(['candidates' => $candidates]);
                    }
                    foreach (($direct ? [] : $crawler->contactLinks($html, $candidate['url'])) as $link) {
                        try {
                            $pages[$link] = $crawler->fetch($link);
                        } catch (Throwable) {
                            $scan->increment('errors');
                        }
                    }
                    foreach ($pages as $source => $body) {
                        if (in_array($scan->fresh()?->status, [null, 'cancelled'], true)) {
                            return;
                        }
                        foreach ($crawler->emails($body, ! $direct) as $email) {
                            ProspectContact::firstOrCreate(['prospect_scan_id' => $scan->id, 'email' => $email], ['company' => $candidate['company'], 'source_url' => $source]);
                        }
                    }
                } catch (Throwable) {
                    $scan->increment('errors');
                }
                $scan->increment('processed');
            }
        }
        if (in_array($scan->fresh()?->status, [null, 'cancelled'], true)) {
            return;
        }
        if ($scan->processed >= count($scan->candidates ?? [])) {
            $scan->update(['status' => 'completed']);
        } else {
            self::dispatch($scan->id)->delay(now()->addSeconds(2));
        }
    }

    public function failed(?Throwable $exception): void
    {
        ProspectScan::whereKey($this->scanId)->where('status', '!=', 'cancelled')->update(['status' => 'failed', 'error' => $exception instanceof ProspectSearchException ? $exception->getMessage() : 'Tarama tamamlanamadı. API anahtarı, kota ve kuyruk işçisini kontrol edin.']);
    }
}
