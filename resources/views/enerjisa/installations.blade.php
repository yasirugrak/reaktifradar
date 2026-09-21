@extends('enerjisa.layout')
@section('title', 'Tesisatlar')
@section('content')
<div class="heading"><div><div class="eyebrow">BAŞKENT EDAŞ</div><h1>Tesisatlarınız</h1><p class="muted">MDM hesabınıza tanımlı tesisatlar ve sayaç bilgileri.</p></div><form method="post" action="{{ route('enerjisa.installations.sync') }}" data-loading>@csrf<button type="submit" @disabled(!$account->client_secret)>Tesisatları getir ↻</button></form></div>
@if(!$account->client_secret)<div class="notice">Tesisatlarınızı getirmek için önce <a href="{{ route('enerjisa.settings') }}">erişim bilgilerinizi kaydedin</a>.</div>@endif
<div class="card">
@if(count($rows))<div class="heading"><h2>{{ count($rows) }} tesisat</h2><span class="muted">Son veri: {{ $last->created_at->format('d.m.Y H:i') }}</span></div>@include('enerjisa.table')
@else<div class="empty"><div class="empty-symbol">⌁</div><h3>{{ $last ? 'Görüntülenebilir tesisat bulunamadı' : 'Tesisat listeniz henüz alınmadı' }}</h3><p class="muted">“Tesisatları getir” ile servisten güncel listenizi alın.</p>@if($last)<a href="{{ route('enerjisa.result', $last->id) }}">Servis yanıtını inceleyin →</a>@endif</div>@endif
</div>
@endsection
