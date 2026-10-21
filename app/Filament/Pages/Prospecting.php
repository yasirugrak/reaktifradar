<?php

namespace App\Filament\Pages;

use App\Jobs\RunProspectScan;
use App\Jobs\SendProspectIntroduction;
use App\Models\ProspectMailDelivery;
use Illuminate\Support\Facades\DB;
use App\Services\ProspectSearch;
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

    public string $provider = 'google';

    public string $sourceUrl = '';

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
        $this->validate(['provider' => 'required|in:google,serper,website', 'sourceUrl' => 'exclude_unless:provider,website|required|url:http,https|max:2048', 'region' => 'exclude_if:provider,website|required|string|max:100', 'osb' => 'nullable|string|max:150', 'sector' => 'nullable|string|max:100', 'limit' => 'required|integer|min:10|max:60']);
        if (! ProspectSearch::configured($this->provider)) {
            Notification::make()->title('Seçilen arama servisinin API ayarlarını tanımlayın.')->warning()->send();

            return;
        }
        $direct = $this->provider === 'website';
        if ($direct) {
            $parts = parse_url($this->sourceUrl);
            if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
                $this->addError('sourceUrl', 'Kullanıcı bilgisi veya özel port içermeyen bir web adresi girin.');
                return;
            }
        }
        $host = $direct ? parse_url($this->sourceUrl, PHP_URL_HOST) : null;
        $scan = ProspectScan::create([
            'provider' => $this->provider, 'source_url' => $direct ? $this->sourceUrl : null,
            'region' => $direct ? $host : $this->region, 'osb' => $direct ? null : $this->osb,
            'sector' => $direct ? null : $this->sector, 'limit' => $direct ? 20 : $this->limit,
            'candidates' => $direct ? [['url' => $this->sourceUrl, 'company' => $host]] : null,
        ]);
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
        $this->provider = $scan->provider ?? 'google';
        $this->sourceUrl = $scan->source_url ?? '';
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

    protected function sendableContacts()
    {
        return ProspectContact::where('prospect_scan_id', $this->scanId)->where('status', 'approved')
            ->whereNotIn('email', ProspectMailDelivery::select('email'))
            ->whereNotIn('email', ProspectContact::select('email')->where('status', 'excluded'));
    }

    public function sendApproved(): void
    {
        abort_unless(static::canAccess(), 403);
        ProspectScan::findOrFail($this->scanId);
        $count = DB::transaction(function () {
            $count = 0;
            foreach ($this->sendableContacts()->get() as $contact) {
                $email = strtolower(trim($contact->email));
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $inserted = DB::table('prospect_mail_deliveries')->insertOrIgnore([
                    'email' => $email, 'prospect_contact_id' => $contact->id, 'status' => 'queued',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($inserted) {
                    $id = ProspectMailDelivery::where('email', $email)->value('id');
                    SendProspectIntroduction::dispatch($id)->delay(now()->addSeconds($count * 3));
                    $count++;
                }
            }
            return $count;
        });
        Notification::make()->title($count.' adres için tanıtım e-postası kuyruğa alındı.')->success()->send();
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

        return ['ready' => ProspectSearch::configured($this->provider), 'scans' => ProspectScan::latest()->limit(30)->get(),
            'scan' => ProspectScan::find($this->scanId), 'total' => (clone $query)->count(),
            'sendableCount' => $this->sendableContacts()->count(),
            'approvedCount' => (clone $query)->where('status', 'approved')->count(),
            'reviewCount' => (clone $query)->where('status', 'review')->count(),
            'contacts' => $query->with('introductionDelivery')->when(in_array($this->filter, ['review', 'approved', 'excluded']), fn ($q) => $q->where('status', $this->filter))->latest()->paginate(20)];
    }
}
