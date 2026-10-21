@if ($paginator->hasPages())
<nav class="pr-pagination" aria-label="Tarama sonuçları sayfaları">
    <p class="prospect-muted">{{ $paginator->total() }} adres · {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</p>
    <div class="pr-page-buttons">
        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" @disabled($paginator->onFirstPage()) aria-label="Önceki sayfa">‹ <span>Önceki</span></button>
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="pr-page-gap">{{ $element }}</span>
            @else
                @foreach ($element as $page => $url)
                    <button type="button" wire:key="prospect-page-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" @if($page === $paginator->currentPage()) aria-current="page" @endif aria-label="Sayfa {{ $page }}">{{ $page }}</button>
                @endforeach
            @endif
        @endforeach
        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" @disabled(! $paginator->hasMorePages()) aria-label="Sonraki sayfa"><span>Sonraki</span> ›</button>
    </div>
</nav>
@endif
