@extends('enerjisa.layout')
@section('title', 'Sorgu sonucu')
@section('content')
@if($owner)<section class="card"><div class="eyebrow">TESİSAT SAHİBİ</div><h2 style="margin-top:8px">{{ $owner }}</h2></section>@endif
<div class="heading"><div><div class="eyebrow">SORGU #{{ $query->id }}</div><h1>{{ $query->label() }}</h1><p class="muted">{{ $query->created_at->format('d.m.Y H:i:s') }} · Ölçüm servisi</p></div><div class="actions">
@if($query->kind === '1' && isset($query->parameters['installationNumber']))<a class="button secondary" href="{{ route('enerjisa.reactive', $analysisFilters) }}">Reaktif analiz →</a>@endif
@if($query->payload !== null && $query->error === null)
@if($rows->total() > 0)<a class="button" href="{{ route('enerjisa.result.download', ['id' => $query->id, 'format' => 'csv']) }}">CSV indir</a>@endif
<a class="button secondary" href="{{ route('enerjisa.result.download', ['id' => $query->id, 'format' => 'json']) }}">JSON indir</a>
@endif
<a class="button secondary" href="{{ route('enerjisa.query') }}">Yeni sorgu</a></div></div>
@if($query->error)<div class="notice error" role="alert">{{ $query->error }}</div>@elseif($query->payload === null)<div class="notice error">Sorgu tamamlanmamış. Lütfen yeni bir sorgu başlatın.</div>@else<div class="notice" role="status">Servis sorgusu tamamlandı. {{ $rows->total() }} görüntülenebilir kayıt alındı.</div>@endif
@if($query->parameters)<section class="card"><h2>Sorgu parametreleri</h2><div class="keyval">@foreach($query->parameters as $key => $value)<span class="muted">{{ $key }}</span><strong>{{ $value }}</strong>@endforeach</div></section>@endif
@if($query->payload !== null)<section class="card"><div class="heading"><h2>Servis verileri</h2><span class="muted">{{ $rows->total() }} kayıt · Sayfa başına 100</span></div>
@if($rows->count())@include('enerjisa.table', ['rows' => $rows->items()])@include('enerjisa.pagination', ['paginator' => $rows])
@else<div class="empty"><h3>Tabloda gösterilecek kayıt bulunamadı</h3><p class="muted">Seçilen aralıkta veri olmayabilir veya servis farklı bir yanıt yapısı kullanıyor olabilir. Ayrıntıyı aşağıdan inceleyin.</p></div>@endif
<p class="hint" style="margin-top:18px">İndirme tüm sorgu kayıtlarını kapsar; ekrandaki sayfayla sınırlı değildir. CSV dosyası UTF-8 ve noktalı virgül ayracı kullanır. Excel’de baştaki sıfırları korumak için tesisat ve sayaç numarası sütunlarını metin olarak içe aktarın. Alan adları ve değerler servis yanıtındaki biçimiyle gösterilir. Birim dönüşümü ve eşik değerlendirmesi yapılmaz.</p>
<details><summary>Servis yanıtını görüntüle (JSON)</summary><pre>{{ json_encode($query->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>
</section>@endif
@endsection
