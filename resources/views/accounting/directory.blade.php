@extends('accounting.layout')
@section('content')
@php($kinds=['person'=>'Kişi','company'=>'Kurum'])
<div class="page-heading"><div><h1>Cari hesaplar</h1><p>İşlemleri görmek için bir kişi veya kurum seçin. Açık hesaplar önce gösterilir.</p></div><a class="button" href="/muhasebe/rehber/ekle">＋ Yeni kayıt</a></div>
<nav class="view-tabs no-print" aria-label="Cari hesap kategorileri">@foreach([''=>'Cari hesaplar','redlined'=>'Kırmızı Kalem Çekilenler','deleted'=>'Silinen Kişiler'] as $value=>$label)<a class="{{request('category','')===$value?'selected':''}}" href="/muhasebe/rehber{{ $value ? '?category='.$value : '' }}">{{$label}}</a>@endforeach</nav><section class="directory-toolbar"><form method="get" id="directory-search-form"><input type="hidden" name="category" value="{{request('category')}}"><div class="grid"><div><label for="search">İsimle ara</label><input id="search" name="q" maxlength="160" autocomplete="off" value="{{request('q')}}" placeholder="Kişi veya kurum adı"></div><div><label for="kind">Kayıt türü</label><select id="kind" name="kind"><option value="">Tüm kayıtlar</option>@foreach($kinds as $value=>$label)<option value="{{$value}}" @selected(request('kind')===$value)>{{$label}}</option>@endforeach</select></div></div><button>Ara</button> <a id="directory-reset" href="/muhasebe/rehber">Filtreleri temizle</a></form></section>
<p id="directory-search-status" role="status" class="search-status"></p><div id="directory-results-panel" aria-busy="false">@include('accounting.directory-results')</div>
@endsection

@push('scripts')<script src="{{asset('js/accounting-contact-search.js')}}" defer></script>@endpush
