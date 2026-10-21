<?php

namespace App\Filament\Pages;

use App\Jobs\RunProspectScan;
use App\Models\ProspectContact;
use App\Models\ProspectScan;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithPagination;
use UnitEnum;

class Prospecting extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.prospecting';

    protected static ?string $title = 'Firma E-posta Keşfi';

    protected static string|UnitEnum|null $navigationGroup = 'ReaktifRadar';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    public string $region = '';

    public string $osb = '';

    public string $sector = '';

    public int $limit = 20;

    public ?int $scanId = null;

    public string $filter = '';

    public static function canAccess(): bool
    {
        return EnerjisaAdmin::canAccess();
    }

    public function start(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['region' => 'required|string|max:100', 'osb' => 'nullable|string|max:150', 'sector' => 'nullable|string|max:100', 'limit' => 'required|integer|min:10|max:60']);
        if (! config('prospecting.google_key') || ! config('prospecting.google_cx')) {
            Notification::make()->title('Önce Google Custom Search API anahtarını ve arama motoru kimliğini (cx) tanımlayın.')->warning()->send();

            return;
        }
        $scan = ProspectScan::create(['region' => $this->region, 'osb' => $this->osb, 'sector' => $this->sector, 'limit' => $this->limit]);
        RunProspectScan::dispatch($scan->id);
        $this->scanId = $scan->id;
        $this->resetPage();
        Notification::make()->title('Tarama kuyruğa alındı.')->success()->send();
    }

    public function selectScan(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $this->scanId = ProspectScan::findOrFail($id)->id;
        $this->resetPage();
    }

    public function cancel(): void
    {
        abort_unless(static::canAccess(), 403);
        ProspectScan::whereKey($this->scanId)->whereIn('status', ['queued', 'running'])->update(['status' => 'cancelled']);
    }

    public function review(int $id, string $status): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(in_array($status, ['review', 'approved', 'excluded']), 422);
        ProspectContact::where('prospect_scan_id', $this->scanId)->findOrFail($id)->update(['status' => $status]);
    }

    public function restartScan(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $scan = ProspectScan::findOrFail($id);
        if (in_array($scan->status, ['queued', 'running'])) {
            Notification::make()->title('Önce devam eden taramayı durdurun.')->warning()->send();

            return;
        }
        $this->region = $scan->region;
        $this->osb = $scan->osb ?? '';
        $this->sector = $scan->sector ?? '';
        $this->limit = $scan->limit;
        $this->filter = '';
        $this->start();
    }

    public function deleteScan(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $scan = ProspectScan::findOrFail($id);
        if (in_array($scan->status, ['queued', 'running'])) {
            Notification::make()->title('Silmeden önce taramayı durdurun.')->warning()->send();

            return;
        }
        $scan->delete();
        if ($this->scanId === $id) {
            $this->scanId = null;
            $this->filter = '';
            $this->resetPage();
        }
        Notification::make()->title('Tarama ve bu taramaya ait adresler silindi.')->success()->send();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function export()
    {
        abort_unless(static::canAccess(), 403);
        $id = $this->scanId;

        return response()->streamDownload(function () use ($id) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Firma', 'E-posta', 'Kaynak', 'Bulunma tarihi'], ';', '"', '');
            foreach (ProspectContact::where('prospect_scan_id', $id)->where('status', 'approved')->cursor() as $contact) {
                $cells = [$contact->company, $contact->email, $contact->source_url, (string) $contact->created_at];
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@\-\s]/u', $v) ? "'".$v : $v, $cells), ';', '"', '');
            }
            fclose($out);
        }, 'firma-eposta-'.$id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $query = ProspectContact::where('prospect_scan_id', $this->scanId);

        return ['ready' => (bool) (config('prospecting.google_key') && config('prospecting.google_cx')), 'scans' => ProspectScan::latest()->limit(30)->get(),
            'scan' => ProspectScan::find($this->scanId), 'total' => (clone $query)->count(),
            'approvedCount' => (clone $query)->where('status', 'approved')->count(),
            'reviewCount' => (clone $query)->where('status', 'review')->count(),
            'contacts' => $query->when(in_array($this->filter, ['review', 'approved', 'excluded']), fn ($q) => $q->where('status', $this->filter))->latest()->paginate(20)];
    }
}
