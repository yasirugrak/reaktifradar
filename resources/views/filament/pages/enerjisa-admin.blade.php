<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px">
    @foreach($counts as $label => $count)<x-filament::section><div style="font-size:13px">{{ $label }}</div><strong style="font-size:30px">{{ $count }}</strong></x-filament::section>@endforeach
    </div>
    <x-filament::section heading="Yönetim">
        <div style="display:flex;gap:20px;flex-wrap:wrap"><x-filament::link :href="\App\Filament\Resources\EnerjisaMemberResource::getUrl()">Kullanıcıları yönet →</x-filament::link><x-filament::link :href="\App\Filament\Resources\EnerjisaAccountResource::getUrl()">Firmaları yönet →</x-filament::link></div>
        <p style="margin-top:12px">Kullanıcı ekleyebilir, bilgilerini ve parolasını değiştirebilir, hesapları kapatıp yeniden açabilirsiniz. Firma kapatıldığında tüm kullanıcı erişimi ve otomatik bildirimleri durur.</p>
    </x-filament::section>
    <x-filament::section heading="Merkezi Telegram kurulumu">
        <p>Telegram’da <strong>@BotFather</strong> hesabını açın, <strong>/newbot</strong> komutuyla bot oluşturun ve verilen token’ı buraya yapıştırın. Bot kullanıcı adı otomatik bulunur ve bağlantı adresi kaydedilir.</p>
        <p style="margin-top:12px">Mevcut bot: <strong>{{ $telegramUsername ? '@'.$telegramUsername : 'Tanımlanmadı' }}</strong>@if($telegramRegisteredAt) · Son kurulum: {{ \Carbon\CarbonImmutable::parse($telegramRegisteredAt)->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}@endif</p>
        <form wire:submit="saveTelegram" style="margin-top:16px;display:grid;gap:12px">
            <label for="admin-telegram-token">Bot token</label>
            <x-filament::input.wrapper><x-filament::input id="admin-telegram-token" type="password" wire:model="token" autocomplete="new-password" /></x-filament::input.wrapper>
            @error('token')<span style="color:#dc2626">{{ $message }}</span>@enderror
            <div><x-filament::button type="submit" wire:loading.attr="disabled">Doğrula ve Telegram’ı etkinleştir</x-filament::button><span wire:loading wire:target="saveTelegram"> Bağlantı kuruluyor…</span></div>
        </form>
        <p style="margin-top:12px;font-size:13px">Token şifreli saklanır ve geri gösterilmez. Farklı bir bota geçerseniz kullanıcıların Telegram hesaplarını yeniden bağlaması gerekir. Kurulum için uygulamanın HTTPS adresi sunucuda tanımlı olmalıdır.</p>
    </x-filament::section>
    <x-filament::section heading="Sistem durumu">
        <p><strong>E-posta:</strong> {{ $smtpHost && $smtpFrom ? 'Merkezi SMTP yapılandırması mevcut' : 'Merkezi SMTP yapılandırması eksik' }} · {{ $smtpHost }} · Gönderen: {{ $smtpFrom }}</p>
        <p>SMTP ayarları sunucunun .env dosyasından yönetilir. Bu bilgi başarılı teslimat testi anlamına gelmez.</p>
        <p style="margin-top:12px"><strong>Zamanlayıcı son kontrolü:</strong> {{ $schedulerRun ? \Carbon\CarbonImmutable::parse($schedulerRun)->timezone('Europe/Istanbul')->format('d.m.Y H:i:s') : 'Henüz çalışmadı' }}</p>
        @if(!$schedulerRun || \Carbon\CarbonImmutable::parse($schedulerRun)->lt(now()->subMinutes(5)))<p style="color:#c2410c">Zamanlayıcıdan güncel kontrol gelmedi. Otomatik gönderim için sunucudaki scheduler servisini kontrol edin.</p>@endif
    </x-filament::section>
    <x-filament::section heading="Son servis hataları">
        @forelse($failures as $failure)<p style="margin-bottom:10px">{{ $failure->created_at->format('d.m.Y H:i') }} · {{ $failure->account?->name ?? 'Firma #'.$failure->account_id }} · {{ $failure->label() }} — {{ $failure->error }}</p>@empty<p>Kayıtlı servis hatası yok.</p>@endforelse
    </x-filament::section>
    <x-filament::section heading="Başarısız veya sonucu beklenen bildirimler">
        @forelse($deliveryFailures as $delivery)<p style="margin-bottom:10px">{{ $delivery->scheduled_date }} · {{ $delivery->rule?->account?->name }} · Tesisat {{ $delivery->rule?->installation }} · {{ $delivery->channel === 'email' ? 'E-posta' : 'Telegram' }} · {{ $delivery->status === 'sending' ? 'Sonucu doğrulanamadı' : 'Gönderilemedi' }} · {{ $delivery->attempts }} deneme — {{ $delivery->error }}</p>@empty<p>Kayıtlı bildirim hatası yok.</p>@endforelse
    </x-filament::section>
</x-filament-panels::page>
