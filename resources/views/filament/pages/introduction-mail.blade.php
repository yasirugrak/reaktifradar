<x-filament-panels::page>
    <x-filament::section heading="İlk izlenim, doğru mesajla başlar." description="Tanıtım e-postasını aşağıdan inceleyin ve kendi gelen kutunuzda test edin.">
        <div style="display:flex;gap:20px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
            <div style="font-size:14px;line-height:1.8;">
                <p><strong>Konu:</strong> {{ $subject }}</p>
                <p><strong>Test alıcısı:</strong> {{ $recipient }}</p>
            </div>
            <x-filament::button wire:click="sendTest" wire:loading.attr="disabled" wire:target="sendTest" icon="heroicon-o-paper-airplane">
                <span wire:loading.remove wire:target="sendTest">Kendime test gönder</span>
                <span wire:loading wire:target="sendTest">Gönderiliyor…</span>
            </x-filament::button>
        </div>
    </x-filament::section>
    <iframe title="Tanıtım e-postası önizlemesi" sandbox="" srcdoc="{{ $preview }}" style="width:100%;height:1100px;border:1px solid #dbe3ec;border-radius:16px;background:#eef2f6;"></iframe>
</x-filament-panels::page>
