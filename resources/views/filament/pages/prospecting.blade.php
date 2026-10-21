<x-filament-panels::page>
    @php($labels = ['queued'=>'Kuyrukta', 'running'=>'Taranıyor', 'completed'=>'Tamamlandı', 'cancelled'=>'Durduruldu', 'failed'=>'Başarısız'])
    <style>
        .pr{--pr-border:#e2e8f0;--pr-soft:#f8fafc;--pr-muted:#64748b;--pr-accent:#4f46e5;--pr-tint:#eef2ff;--pr-text:#0f172a;color:var(--pr-text)}
        .dark .pr{--pr-border:#334155;--pr-soft:#182132;--pr-muted:#a8b6cb;--pr-accent:#a5b4fc;--pr-tint:#252747;--pr-text:#f1f5f9}
        .pr button,.pr a,.pr input,.pr select{transition:background .15s,border-color .15s;touch-action:manipulation}.pr :focus-visible{outline:3px solid #818cf8;outline-offset:3px}
        .prospect-grid{display:grid;grid-template-columns:300px minmax(0,1fr);gap:24px;align-items:start;margin-top:24px}.prospect-form{display:grid;gap:18px}.prospect-form label{font-size:13px;font-weight:600}.prospect-input{display:block;width:100%;border:1px solid var(--pr-border);border-radius:10px;padding:11px 12px;background:var(--pr-soft);color:inherit;margin-top:7px;font-size:14px;font-weight:400}.prospect-input:focus{border-color:#818cf8}.prospect-muted{font-size:13px;color:var(--pr-muted);line-height:1.65}.prospect-table{width:100%;text-align:left;font-size:13px;border-collapse:collapse}.prospect-table td,.prospect-table th{padding:17px 14px;border-bottom:1px solid var(--pr-border);vertical-align:top}.prospect-table th{font-size:11px;letter-spacing:.04em;color:var(--pr-muted);background:var(--pr-soft)}.prospect-table tbody tr:hover{background:var(--pr-soft)}.prospect-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.prospect-history{display:grid;gap:8px;margin-top:24px}.prospect-history>strong{font-size:12px;color:var(--pr-muted);margin-bottom:4px}.prospect-history>button{text-align:left;padding:14px;border:1px solid var(--pr-border);border-radius:12px;background:var(--pr-soft)}.prospect-history>button[aria-pressed="true"]{border-color:#818cf8;background:var(--pr-tint)}.prospect-history>button:hover{border-color:#818cf8}.prospect-link{color:var(--pr-accent);word-break:break-all;font-weight:500}.prospect-link:hover{text-decoration:underline}
        .pr-hero{display:flex;justify-content:space-between;align-items:center;gap:24px;padding:28px 30px;border:1px solid var(--pr-border);border-radius:16px;background:linear-gradient(115deg,var(--pr-tint),var(--pr-soft))}.pr-eyebrow{font-size:11px;font-weight:700;letter-spacing:.12em;color:var(--pr-accent);margin-bottom:8px}.pr-hero h2{font-size:25px;font-weight:700;letter-spacing:-.035em;margin-bottom:8px}.pr-badge{display:inline-flex;align-items:center;gap:7px;padding:5px 10px;border-radius:20px;background:var(--pr-soft);font-size:11px;font-weight:600;white-space:nowrap;border:1px solid var(--pr-border)}.pr-badge:before{content:'';height:6px;width:6px;border-radius:50%;background:currentColor}.pr-badge.approved,.pr-badge.completed{color:#15803d;background:#f0fdf4;border-color:#bbf7d0}.pr-badge.running{color:#4338ca;background:#eef2ff;border-color:#c7d2fe}.pr-badge.review,.pr-badge.queued{color:#a16207;background:#fffbeb;border-color:#fde68a}.pr-badge.failed{color:#be123c;background:#fff1f2;border-color:#fecdd3}.pr-badge.excluded,.pr-badge.cancelled{color:var(--pr-muted)}
        .pr-connect{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 20px;margin-top:16px;background:var(--pr-soft);border:1px dashed var(--pr-border);border-radius:12px}.pr-connect strong{font-size:14px}.pr-help{padding:12px;border-radius:10px;background:var(--pr-soft)}.pr-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:24px}.pr-stat{padding:16px 12px;border:1px solid var(--pr-border);border-radius:12px}.pr-stat strong{display:block;font-size:26px;letter-spacing:-.04em;line-height:1.3}.pr-stat span{font-size:11px;color:var(--pr-muted)}.pr-toolbar{display:flex;justify-content:space-between;gap:16px;align-items:center;padding:16px 0}.pr-toolbar .prospect-input{width:auto;min-width:180px;margin:0}.pr-progress{height:6px;border-radius:8px;overflow:hidden;background:var(--pr-border);margin:12px 0}.pr-progress>div{height:100%;background:#6366f1;border-radius:8px;transition:width .3s}.pr-company{font-weight:600;max-width:280px;overflow-wrap:anywhere;margin-bottom:5px}.pr-email{font-family:ui-monospace,monospace;font-size:12px;overflow-wrap:anywhere}.pr-row-actions{margin-top:10px;display:flex;gap:6px;flex-wrap:wrap}.pr-row-actions button{border:1px solid var(--pr-border);border-radius:6px;padding:4px 7px;font-size:11px;white-space:nowrap}.pr-row-actions button:hover{background:var(--pr-tint);border-color:#818cf8}.pr-empty{padding:64px 20px;text-align:center}.pr-empty-icon{width:64px;height:64px;padding:17px;border-radius:18px;background:var(--pr-tint);color:var(--pr-accent);margin:0 auto 18px}.pr-empty strong{display:block;font-size:18px;margin-bottom:8px}.pr-empty p{max-width:360px;margin:auto}.pr-footer{margin-top:16px}.pr-error{color:#e11d48;font-size:12px}.pr-table-wrap{overflow:auto;border:1px solid var(--pr-border);border-radius:12px}
        @media(max-width:1100px){.prospect-grid{grid-template-columns:1fr}.prospect-history{max-height:240px;overflow:auto}.pr-summary{margin-top:4px}}@media(max-width:600px){.pr-hero{padding:20px;align-items:flex-start;flex-direction:column;gap:14px}.pr-hero h2{font-size:22px}.pr-connect,.pr-toolbar{align-items:stretch;flex-direction:column}.pr-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.prospect-table{min-width:620px}.pr-toolbar .prospect-input{width:100%}}
        @media(prefers-reduced-motion:reduce){.pr *{transition:none!important}}
    </style>
    <div class="pr">
    <div class="pr-hero">
        <div><div class="pr-eyebrow">FİRMA KEŞFİ</div><h2>Bir sonraki müşterinizi bulun.</h2><p class="prospect-muted">Bölgenizi seçin. Kurumsal iletişim adreslerini keşfedin, inceleyin ve listenizi oluşturun.</p></div>
        <span class="pr-badge {{ $ready ? 'approved' : 'queued' }}">{{ $ready ? 'Google API ayarları tanımlı' : 'Bağlantı kurulmadı' }}</span>
    </div>
    @unless($ready)
        <div class="pr-connect"><div><strong>Google arama bağlantısını tanımlayın</strong><p class="prospect-muted">Custom Search JSON API anahtarı ve Programmable Search Engine kimliği (cx) gereklidir.</p></div><a class="prospect-link" href="https://console.cloud.google.com/apis/library/customsearch.googleapis.com" target="_blank" rel="noopener noreferrer">Google Cloud’u aç ↗</a></div>
        <details class="prospect-muted" style="margin-top:10px"><summary style="cursor:pointer">Kurulum adımları</summary><p>Sunucunun .env dosyasına GOOGLE_SEARCH_API_KEY ve GOOGLE_SEARCH_ENGINE_ID (cx) ekleyin. Arama motoru kimliğini Programmable Search Engine panelinden alın. Ardından php artisan config:cache ve php artisan queue:restart çalıştırın. Veritabanı kuyruk işçisi çalışır durumda olmalıdır.</p></details>
    @endunless
    <div class="prospect-grid" wire:poll.5s>
        <div>
            <x-filament::section heading="Tarama oluştur">
                <form wire:submit="start" class="prospect-form">
                    <label>İl / bölge <input class="prospect-input" wire:model="region" placeholder="Örn. Kocaeli, Gebze" required maxlength="100"> @error('region') <span class="pr-error">{{ $message }}</span> @enderror</label>
                    <label>Organize sanayi bölgesi <input class="prospect-input" wire:model="osb" placeholder="Örn. Gebze OSB" maxlength="150"> @error('osb') <span class="pr-error">{{ $message }}</span> @enderror</label>
                    <label>Sektör (isteğe bağlı) <input class="prospect-input" wire:model="sector" placeholder="Örn. metal, otomotiv, plastik" maxlength="100"> @error('sector') <span class="pr-error">{{ $message }}</span> @enderror</label>
                    <label>En fazla aday site <select class="prospect-input" wire:model="limit"><option value="20">20 site</option><option value="40">40 site</option><option value="60">60 site</option></select> @error('limit') <span class="pr-error">{{ $message }}</span> @enderror</label>
                    <p class="prospect-muted pr-help">Her tarama 20 / 40 / 60 aday için en fazla 2 / 4 / 6 Google arama API isteği kullanır. Aynı sitede en fazla 2 sayfa okunur; info, satış, ihracat gibi kurumsal adresler alınır.</p>
                    <x-filament::button type="submit" :disabled="!$ready" wire:loading.attr="disabled" wire:target="start"><span wire:loading.remove wire:target="start">Firmaları keşfet →</span><span wire:loading wire:target="start">Tarama hazırlanıyor…</span></x-filament::button>
                </form>
            </x-filament::section>
            <div class="prospect-history">
                <strong>Son taramalar</strong>
                @forelse($scans as $item)
                    <button wire:click="selectScan({{ $item->id }})" wire:key="scan-{{ $item->id }}" aria-pressed="{{ $scanId === $item->id ? 'true' : 'false' }}"><strong>{{ $item->region }}</strong><br><span class="prospect-muted">{{ $item->osb }} · {{ $item->created_at->timezone('Europe/Istanbul')->format('d.m H:i') }} · {{ $labels[$item->status] ?? $item->status }}</span></button>
                @empty <p class="prospect-muted">Henüz tarama yok.</p> @endforelse
            </div>
        </div>
        <x-filament::section heading="Firma ve iletişim listesi">
            @if($scan)
                <div class="pr-summary">
                    <div class="pr-stat"><strong>{{ $total }}</strong><span>Bulunan e-posta</span></div>
                    <div class="pr-stat"><strong>{{ $reviewCount }}</strong><span>İnceleme bekleyen</span></div>
                    <div class="pr-stat"><strong style="color:var(--pr-accent)">{{ $approvedCount }}</strong><span>Listeye onaylanan</span></div>
                    <div class="pr-stat"><strong>{{ $scan->processed }}</strong><span>Taranan site</span></div>
                </div>
                <div class="prospect-actions" style="justify-content:space-between;margin-bottom:16px">
                    <div><span class="pr-badge {{ $scan->status }}">{{ $labels[$scan->status] ?? $scan->status }}</span><p class="prospect-muted">{{ $scan->processed }} / {{ count($scan->candidates ?? []) }} site · {{ $total }} adres · {{ $scan->errors }} okunamayan sayfa</p></div>
                    @if(in_array($scan->status, ['queued','running'])) <x-filament::button color="gray" wire:click="cancel">Durdur</x-filament::button> @endif
                    @unless(in_array($scan->status, ['queued', 'running']))
                        <x-filament::button color="gray" wire:click="restartScan({{ $scan->id }})" wire:confirm="Aynı ayarlarla yeni bir tarama başlatılsın mı? Mevcut sonuçlar korunacak ve Google arama kotası kullanılacak." :disabled="!$ready" wire:loading.attr="disabled">Yeniden başlat</x-filament::button>
                        <x-filament::button color="danger" wire:click="deleteScan({{ $scan->id }})" wire:confirm="Bu tarama ve onaylananlar dahil tüm adresleri kalıcı olarak silinecek. Devam edilsin mi?" wire:loading.attr="disabled">Sil</x-filament::button>
                    @else
                        <span class="prospect-muted">Yeniden başlatmak veya silmek için önce durdurun.</span>
                    @endunless
                    <x-filament::button wire:click="export" :disabled="$approvedCount === 0" wire:loading.attr="disabled" wire:target="export">Onaylananları CSV indir</x-filament::button>
                </div>
                <div class="pr-progress" role="progressbar" aria-label="Tarama ilerlemesi" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $scan->status === 'completed' ? 100 : round(100 * $scan->processed / max(1, count($scan->candidates ?? []))) }}"><div style="width:{{ $scan->status === 'completed' ? 100 : round(100 * $scan->processed / max(1, count($scan->candidates ?? []))) }}%"></div></div>
                @if($scan->status === 'queued') <p class="prospect-muted">Tarama işçisi bekleniyor. Bu durum uzun sürerse kuyruk işçisini kontrol edin.</p> @endif
                @if($scan->error) <p role="alert" class="pr-error">{{ $scan->error }}</p> @endif
                <div class="pr-toolbar"><p class="prospect-muted">{{ $scan->region }} @if($scan->osb) / {{ $scan->osb }} @endif</p><select aria-label="Liste durumu" class="prospect-input" wire:model.live="filter"><option value="">Tüm adresler</option value="review">İnceleme bekleyen</option><option value="approved">Listeye onaylanan</option><option value="excluded">Hariç tutulan</option></select></div>
                <div class="pr-table-wrap">
                    <table class="prospect-table"><thead><tr><th>Firma / kaynak</th><th>E-posta</th><th>İnceleme</th></tr></thead><tbody>
                        @forelse($contacts as $contact)
                            <tr wire:key="contact-{{ $contact->id }}"><td><div class="pr-company">{{ $contact->company }}</div><a class="prospect-link" href="{{ $contact->source_url }}" target="_blank" rel="noopener noreferrer">Kaynağı incele ↗</a></td><td class="pr-email">{{ $contact->email }}</td><td>
                                <p class="pr-badge {{ $contact->status }}">{{ ['review'=>'Bekliyor','approved'=>'Onaylandı','excluded'=>'Hariç tutuldu'][$contact->status] }}</p>
                                <div class="pr-row-actions"><button class="prospect-link" wire:click="review({{ $contact->id }}, 'approved')">Onayla</button><button wire:click="review({{ $contact->id }}, 'excluded')">Hariç tut</button><button wire:click="review({{ $contact->id }}, 'review')">Sıfırla</button></div>
                            </td></tr>
                        @empty <tr><td colspan="3">Bu görünümde adres bulunamadı. Tarama sürüyorsa sonuçlar otomatik görünür.</td></tr> @endforelse
                    </tbody></table>
                </div>
                <div class="pr-footer">{{ $contacts->links() }}</div>
            @else
                <div class="pr-empty"><div class="pr-empty-icon"><x-heroicon-o-building-office-2 /></div><strong>Firmanız için yeni bağlantılar</strong><p class="prospect-muted">İl veya bölgeyi girin, OSB ve sektörle aramayı daraltın. Bulunan iletişim adresleri burada listelenecek.</p></div>
            @endif
        </x-filament::section>
    </div>
    <p class="prospect-muted pr-footer">Firma büyüklüğü ve OSB üyeliği kaynak üzerinden doğrulanmalıdır. Liste onayı, tanıtım e-postası gönderim izni anlamına gelmez.</p>
    </div>
</x-filament-panels::page>
