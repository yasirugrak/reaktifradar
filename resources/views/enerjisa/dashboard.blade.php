@extends('enerjisa.layout')
@section('title', 'Genel bakış')
@section('content')
<div class="heading"><div><div class="eyebrow">ENERJİ ÇALIŞMA ALANI</div><h1>Genel bakış</h1><p class="muted">Tesisatlarınız, bağlantınız ve son veri sorgularınız.</p></div><a class="button" href="{{ route('enerjisa.query') }}">Yeni sorgu →</a></div>
@if(!$account->connected_at)
<div class="card hero"><div><div class="eyebrow">BAĞLANTIYI TAMAMLAYIN</div><h2 style="font-size:22px;margin-top:10px">Verilerinize bağlanarak başlayın</h2><p class="muted">Enerjisa'nın ilettiği MDM kullanıcı adı ve parolasını ekleyin,<br>bağlantıyı test edin ve tesisatlarınızı getirin.</p></div><a class="button yellow" href="{{ route('enerjisa.settings') }}">Erişim bilgileri →</a></div>
@endif
<div class="grid">
    <div class="card"><div class="metric-label">KAYITLI TESİSAT</div><div class="metric">{{ $installationCount }}</div><span class="muted">Son başarılı tesisat listesinden</span></div>
    <div class="card"><div class="metric-label">TOPLAM SORGU</div><div class="metric">{{ $total }}</div><span class="muted">{{ $failures }} başarısız servis sorgusu</span></div>
    <div class="card"><div class="metric-label">BAĞLANTI TESTİ</div><div class="metric" style="font-size:22px">{{ $account->connected_at ? 'Doğrulandı' : 'Bekliyor' }}</div><span class="muted">{{ $account->connected_at?->format('d.m.Y H:i') ?? 'Erişim bilgilerini test edin' }}</span></div>
</div>
<div class="card"><div class="heading" style="margin-bottom:10px"><h2>Son sorgular</h2><span class="muted">Firma geçmişi</span></div>
@if($queries->isEmpty())
<div class="empty"><div class="empty-symbol">↗</div><h3>Henüz bir sorgunuz yok</h3><p class="muted">Önce tesisat listenizi getirin, ardından incelemek istediğiniz veriyi seçin.</p><a class="button secondary" href="{{ route('enerjisa.installations') }}">Tesisatlara git</a></div>
@else
<div class="table-wrap"><table><thead><tr><th>SORGU TÜRÜ</th><th>TESİSAT</th><th>TARİH</th><th>DURUM</th><th></th></tr></thead><tbody>
@foreach($queries as $query)<tr><td>{{ $query->label() }}</td><td>{{ $query->parameters['installationNumber'] ?? $query->parameters['installationNumbers'] ?? 'Tüm tesisatlar' }}</td><td>{{ $query->created_at->format('d.m.Y H:i') }}</td><td><span class="status {{ $query->error ? 'error' : '' }}">{{ $query->error ? 'Başarısız' : ($query->payload !== null ? 'Tamamlandı' : 'Tamamlanmadı') }}</span></td><td><a href="{{ route('enerjisa.result', $query->id) }}">İncele →</a></td></tr>@endforeach
</tbody></table></div>
@include('enerjisa.pagination', ['paginator' => $queries])
@endif
</div>
<div class="muted" style="font-size:12px">Reaktif analiz bölümünde eşik aşımlarını inceleyebilir, Bildirimler bölümünde düzenli veri kontrolü ve durum mesajlarını yapılandırabilirsiniz.</div>
@endsection
