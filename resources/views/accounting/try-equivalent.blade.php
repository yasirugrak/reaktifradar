@if($fxCurrency!=='TRY')
@php($fxRate=$exchangeRates['rates'][$fxCurrency]??null)
@if($fxRate)<small class="try-equivalent" title="{{$fxRate['source']}} · {{$fxRate['date']}} · Satış kuru{{ $fxRate['stale'] ? ' · Son alınan veri' : '' }}">≈ {{number_format(($fxAmount/10000)*$fxRate['sell'],2,',','.')}} TL{{$fxRate['stale']?' · Eski kur':''}}</small>@else<small class="try-equivalent">TL karşılığı: kur alınamadı</small>@endif
@endif
