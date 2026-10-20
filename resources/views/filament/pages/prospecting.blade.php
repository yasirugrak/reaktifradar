<x-filament-panels::page>
    <style>
        .prospect-grid{display:grid;grid-template-columns:320px minmax(0,1fr);gap:24px}.prospect-form{display:grid;gap:16px}.prospect-input{display:block;width:100%;border:1px solid #94a3b8;border-radius:8px;padding:10px;background:transparent;color:inherit;margin-top:6px}.prospect-muted{font-size:13px;opacity:.7}.prospect-table{width:100%;text-align:left;font-size:14px}.prospect-table td,.prospect-table th{padding:12px;border-bottom:1px solid #94a3b840}.prospect-actions{display:flex;gap:8px;flex-wrap:wrap}.prospect-history{display:grid;gap:10px;margin-top:20px}.prospect-history button{text-align:left;padding:12px;border:1px solid #94a3b840;border-radius:8px}.prospect-link{color:#6366f1;word-break:break-all}@media(max-width:1000px){.prospect-grid{grid-template-columns:1fr}}
    </style>
    <x-filament::section heading="Yeni firmalara ulaşın" description="Bölge ve OSB ile arayın; herkese açık kurumsal iletişim adreslerini kaynaklarıyla inceleyin.">
        <p class="prospect-muted">Arama, üretim ve fabrika odaklıdır. Firma büyüklüğü ve OSB üyeliği otomatik doğrulanmaz. Bulunan adresler inceleme bekler; listeye onaylamak gönderim izni anlamına gelmez.</p>
    </x-filament::section>
    @unless($ready)
        <x-filament::section heading="Arama bağlantısı bekleniyor">Sunucu ayarlarına BRAVE_SEARCH_API_KEY eklenmeli ve veritabanı kuyruğu işçisi çalıştırılmalıdır.</x-filament::section>
    @endunless
    <div class="prospect-grid" wire:poll.5s>
        <div>
            <x-filament::section heading="Tarama oluştur">
                <form wire:submit="start" class="prospect-form">
                    <label>İl / bölge <input class="prospect-input" wire:model="region" placeholder="Örn. Kocaeli, Gebze" required maxlength="100"> @error('region') <span>{{ $message }}</span> @enderror</label>
                    <label>Organize sanayi bölgesi <input class="prospect-input" wire:model="osb" placeholder="Örn. Gebze OSB" maxlength="150"> @error('osb') <span>{{ $message }}</span> @enderror</label>
                    <label>Sektör (isteğe bağlı) <input class="prospect-input" wire:model="sector" placeholder="Örn. metal, otomotiv, plastik" maxlength="100"> @error('sector') <span>{{ $message }}</span> @enderror</label>
                    <label>En fazla aday site <select class="prospect-input" wire:model="limit"><option value="20">20 site</option><option value="40">40 site</option><option value="60">60 site</option></select> @error('limit') <span>{{ $message }}</span> @enderror</label>
                    <p class="prospect-muted">Her tarama en fazla 3 arama API isteği kullanır. Aynı sitede en fazla 2 sayfa okunur; info, satış, ihracat gibi kurumsal adresler alınır.</p>
                    <x-filament::button type="submit" :disabled="!$ready" wire:loading.attr="disabled" wire:target="start">Firmaları keşfet</x-filament::button>
                </form>
            </x-filament::section>
            <div class="prospect-history">
                <strong>Son taramalar</strong>
                @forelse($scans as $item)
                    <button wire:click="selectScan({{ $item->id }})" wire:key="scan-{{ $item->id }}" style="{{ $scanId === $item->id ? 'border-color:#6366f1' : '' }}"><strong>{{ $item->region }}</strong><br><span class="prospect-muted">{{ $item->osb }} · {{ $item->created_at->format('d.m H:i') }}</span></button>
                @empty <p class="prospect-muted">Henüz tarama yok.</p> @endforelse
            </div>
        </div>
        <x-filament::section heading="Firma ve iletişim listesi">
            @if($scan)
                @php($labels = ['queued'=>'Kuyrukta', 'running'=>'Taranıyor', 'completed'=>'Tamamlandı', 'cancelled'=>'Durduruldu', 'failed'=>'Başarısız'])
                <div class="prospect-actions" style="justify-content:space-between;margin-bottom:16px">
                    <div><strong>{{ $labels[$scan->status] ?? $scan->status }}</strong><p class="prospect-muted">{{ $scan->processed }} / {{ count($scan->candidates ?? []) }} site · {{ $total }} adres · {{ $scan->errors }} okunamayan sayfa</p></div>
                    @if(in_array($scan->status, ['queued','running'])) <x-filament::button color="gray" wire:click="cancel">Durdur</x-filament::button> @endif
                    <x-filament::button wire:click="export">Onaylananları CSV indir</x-filament::button>
                </div>
                @if($scan->status === 'queued') <p class="prospect-muted">Tarama işçisi bekleniyor. Bu durum uzun sürerse kuyruk işçisini kontrol edin.</p> @endif
                @if($scan->error) <p role="alert">{{ $scan->error }}</p> @endif
                <select aria-label="Liste durumu" class="prospect-input" wire:model.live="filter"><option value="">Tüm adresler</option value="review">İnceleme bekleyen</option><option value="approved">Listeye onaylanan</option><option value="excluded">Hariç tutulan</option></select>
                <div style="overflow:auto;margin-top:16px">
                    <table class="prospect-table"><thead><tr><th>Firma / kaynak</th><th>E-posta</th><th>İnceleme</th></tr></thead><tbody>
                        @forelse($contacts as $contact)
                            <tr wire:key="contact-{{ $contact->id }}"><td>{{ $contact->company }}<br><a class="prospect-link" href="{{ $contact->source_url }}" target="_blank" rel="noopener noreferrer">Kaynağı incele ↗</a></td><td>{{ $contact->email }}</td><td>
                                <p class="prospect-muted">{{ ['review'=>'Bekliyor','approved'=>'Onaylandı','excluded'=>'Hariç tutuldu'][$contact->status] }}</p>
                                <div class="prospect-actions"><button class="prospect-link" wire:click="review({{ $contact->id }}, 'approved')">Onayla</button><button wire:click="review({{ $contact->id }}, 'excluded')">Hariç tut</button><button wire:click="review({{ $contact->id }}, 'review')">Sıfırla</button></div>
                            </td></tr>
                        @empty <tr><td colspan="3">Bu görünümde adres bulunamadı. Tarama sürüyorsa sonuçlar otomatik görünür.</td></tr> @endforelse
                    </tbody></table>
                </div>
                {{ $contacts->links() }}
            @else
                <div style="padding:64px 16px;text-align:center"><strong>İlk firma listenizi oluşturun</strong><p class="prospect-muted">Bölgeyi girerek taramayı başlatın veya geçmiş taramalardan birini seçin.</p></div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
