@extends('enerjisa.layout')
@section('title', 'Veri sorgulama')
@section('content')
@php($latestDate = (string) old('kind', request('kind', 'hourly')) === '1' ? now('Europe/Istanbul') : now('Europe/Istanbul')->subDay())
<div class="heading"><div><div class="eyebrow">MANUEL SORGU</div><h1>Enerji verilerini sorgula</h1><p class="muted">Tesisat, veri türü ve tarih aralığını seçin.</p></div></div>
@if(!$account->client_secret)<div class="notice">Sorgulamadan önce <a href="{{ route('enerjisa.settings') }}">Enerjisa erişim bilgilerini kaydedin</a>.</div>@endif
<div class="two-col"><section class="card"><h2>Sorgu bilgileri</h2>
<form method="post" action="{{ route('enerjisa.query.run') }}" data-loading>@csrf
<div class="field"><label for="installation">Tesisat numarası</label>@if(count($installations))<select id="installation" name="installation" required><option value="">Tesisat seçin</option>@foreach($installations as $installation)@php($number = (string) ($installation['installationNumber'] ?? $installation['instalationNumber']))<option value="{{ $number }}" @selected((string) old('installation', request('installation')) === $number)>{{ $number }} · {{ $installation['customerName'] ?? '' }}</option>@endforeach</select>@else<input id="installation" name="installation" value="{{ old('installation', request('installation')) }}" required maxlength="64" placeholder="Tesisat numarası">@endif<div class="hint">Yalnızca Enerjisa hesabınızın yetkili olduğu tesisatlar sorgulanabilir.</div></div>
<div class="field"><label for="kind">Veri türü</label><select id="kind" name="kind">@foreach(['hourly' => 'Saatlik tüketim / üretim', '1' => 'Saatlik endeks (yük profili)', '2' => 'Günlük endeks', '3' => 'Reset verisi'] as $value => $label)<option value="{{ $value }}" @selected(old('kind', request('kind', 'hourly')) == $value)>{{ $label }}</option>@endforeach</select></div>
<div id="hourly-fields"><div class="field"><label for="month">Veri ayı</label><input id="month" name="month" type="month" value="{{ old('month', now()->subMonthNoOverflow()->format('Y-m')) }}" max="{{ now()->format('Y-m') }}"></div><div class="field"><label for="from_date">Delta başlangıcı <span class="muted">(isteğe bağlı)</span></label><input id="from_date" name="from_date" type="datetime-local" value="{{ old('from_date') }}" max="{{ now()->subDay()->format('Y-m-d') }}T23:59"><div class="hint">Boş bırakırsanız seçilen ayın ilk günü 00:00 kullanılır. Doldurursanız bu zamandan sonraki kayıtlar istenir.</div></div></div>
<div id="energy-fields"><div class="form-grid"><div class="field"><label for="start">Başlangıç tarihi</label><input id="start" name="start" type="date" value="{{ old('start', request('start', $latestDate->copy()->startOfMonth()->format('Y-m-d'))) }}" max="{{ $latestDate->format('Y-m-d') }}"></div><div class="field"><label for="end">Bitiş tarihi</label><input id="end" name="end" type="date" value="{{ old('end', request('end', $latestDate->format('Y-m-d'))) }}" max="{{ $latestDate->format('Y-m-d') }}"></div></div></div>
<button type="submit" @disabled(!$account->client_secret)>Verileri getir →</button>
</form></section>
<div><section class="card"><h2>Hangi veriyi arıyorsunuz?</h2><div class="steps"><div><h3>Saatlik tüketim / üretim</h3><p class="muted">Seçilen aya ait aktif tüketim ve üretim kayıtları.</p></div><div><h3>Endeks verileri</h3><p class="muted">Saatlik yük profili veya günlük sayaç endeksleri. Tek sorguda en fazla 1 ay.</p></div><div><h3>Reset verisi</h3><p class="muted">Sayaç reset kayıtları. Tek sorguda en fazla 1 yıl.</p></div></div></section><p class="muted" style="font-size:12px">Saatlik endekste bugünün verisi de istenebilir; kullanılabilirlik servisin yanıtına bağlıdır. Sonuçlar sorgu geçmişine kaydedilir; otomatik alarm üretilmez.</p></div></div>
@endsection
@push('scripts')
<script>
const kind = document.getElementById('kind');
function setQueryFields() {
    const hourly = kind.value === 'hourly';
    ['start','end'].forEach(id => document.getElementById(id).max = kind.value === '1' ? '{{ now('Europe/Istanbul')->format('Y-m-d') }}' : '{{ now('Europe/Istanbul')->subDay()->format('Y-m-d') }}');
    document.getElementById('hourly-fields').hidden = !hourly;
    document.getElementById('energy-fields').hidden = hourly;
    ['month','from_date'].forEach(id => document.getElementById(id).disabled = !hourly);
    ['start','end'].forEach(id => { document.getElementById(id).disabled = hourly; document.getElementById(id).required = !hourly; });
    document.getElementById('month').required = hourly;
}
kind.addEventListener('change', setQueryFields); setQueryFields();
</script>
@endpush
