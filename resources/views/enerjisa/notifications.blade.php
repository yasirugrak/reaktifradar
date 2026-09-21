@extends('enerjisa.layout')
@section('title', 'Bildirimler')
@section('content')
<div class="heading"><div><div class="eyebrow">DÜZENLİ DURUM RAPORLARI</div><h1>Tesisat bildirimleri</h1><p class="muted">Hangi tesisatın, ne zaman ve hangi kanaldan haber vereceğini belirleyin.</p></div></div>
<div class="two-col">
<section class="card"><h2>Tesisat ayarları</h2>
<form method="get" action="{{ route('enerjisa.notifications') }}"><div class="field"><label for="choose-site">Tesisat</label><select id="choose-site" name="installation" onchange="this.form.submit()"><option value="">Tesisat seçin</option>@foreach($installations as $number => $owner)<option value="{{ $number }}" @selected((string)$number === $selected)>{{ $number }} · {{ $owner }}</option>@endforeach</select></div><noscript><button>Seç</button></noscript></form>
@if(isset($installations[$selected]))
<p><strong>{{ $installations[$selected] }}</strong><br><span class="muted">Tesisat {{ $selected }}</span></p>
<form method="post" action="{{ route('enerjisa.notifications.save') }}" data-loading>@csrf
<input type="hidden" name="installation" value="{{ $selected }}">
<div class="field"><label for="enabled">Bildirim durumu</label><select name="enabled" id="enabled"><option value="0" @selected(!old('enabled', $rule?->enabled))>Kapalı</option><option value="1" @selected(old('enabled', $rule?->enabled))>Açık</option></select></div>
<div class="form-grid"><div class="field"><label for="frequency">Gönderim sıklığı</label><select name="frequency" id="frequency"><option value="daily" @selected(old('frequency', $rule?->frequency) !== 'weekly')>Günlük</option><option value="weekly" @selected(old('frequency', $rule?->frequency) === 'weekly')>Haftalık</option></select></div><div class="field"><label for="send-time">Gönderim saati · İstanbul</label><input id="send-time" type="time" name="send_time" value="{{ old('send_time', $rule?->send_time ?? '09:00') }}" required></div></div>
<div class="field" id="weekday-field"><label for="weekday">Haftanın günü</label><select id="weekday" name="weekday">@foreach([1=>'Pazartesi',2=>'Salı',3=>'Çarşamba',4=>'Perşembe',5=>'Cuma',6=>'Cumartesi',7=>'Pazar'] as $day=>$label)<option value="{{ $day }}" @selected((int)old('weekday', $rule?->weekday ?? 1) === $day)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label for="healthy">Hangi durumlarda gönderilsin?</label><select id="healthy" name="send_healthy"><option value="0" @selected(!old('send_healthy', $rule?->send_healthy))>Yalnızca eşik aşımı veya veri sorunu olduğunda</option><option value="1" @selected(old('send_healthy', $rule?->send_healthy))>Her dönem · sorun yoksa da haber ver</option></select></div>
<div class="form-grid"><div><div class="field"><label for="email-enabled">E-posta</label><select id="email-enabled" name="email_enabled"><option value="0" @selected(!old('email_enabled', $rule?->email_enabled))>Gönderme</option><option value="1" @selected(old('email_enabled', $rule?->email_enabled))>Gönder</option></select></div><div class="field"><label for="recipient">Alıcı e-posta adresi</label><input id="recipient" type="email" name="email" value="{{ old('email', $rule?->email) }}" maxlength="255"></div></div>
<div><div class="field"><label for="telegram-enabled">Telegram</label><select id="telegram-enabled" name="telegram_enabled"><option value="0" @selected(!old('telegram_enabled', $rule?->telegram_enabled))>Gönderme</option><option value="1" @selected(old('telegram_enabled', $rule?->telegram_enabled))>Gönder</option></select></div><div class="hint">{{ $rule?->telegram_connected_at ? 'Telegram hesabınız bağlı.' : 'Gönderim için önce Telegram hesabınızı bağlayın.' }}</div></div></div>
<button type="submit">Tesisat ayarlarını kaydet</button>
</form>
@else<div class="empty"><h3>Tesisat seçerek başlayın</h3><p class="muted">Her tesisat için ayrı bildirim kuralı kaydedebilirsiniz.</p><a href="{{ route('enerjisa.installations') }}">Tesisat listesini getir →</a></div>@endif
</section>
<div><section class="card"><h2>Telegram bağlantısı</h2><p class="muted">Bu tesisatın durum mesajlarını Telegram hesabınızda alın.</p>
@if(isset($installations[$selected]))
@if($rule?->telegram_connected_at)<p><span class="status">Telegram bağlı</span></p><p class="muted">Bağlantı tarihi: {{ $rule->telegram_connected_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>
<form method="post" action="{{ route('enerjisa.notifications.disconnect') }}">@csrf<input type="hidden" name="installation" value="{{ $selected }}"><button class="secondary">Bağlantıyı kaldır</button></form>
@else<p class="muted">1. Aşağıdaki düğmeyle Telegram’ı açın.<br>2. Sohbette <strong>Başlat</strong> düğmesine basın.<br>3. Panele dönüp Telegram gönderimini açın ve kaydedin.</p>
@if($telegramReady)<form method="post" action="{{ route('enerjisa.notifications.connect') }}">@csrf<input type="hidden" name="installation" value="{{ $selected }}"><button>Telegram’ı bağla →</button></form><div class="hint">Bağlantı 15 dakika geçerlidir. Bu hesabı yalnızca kendiniz bağlayın.</div>@else<p class="notice">Telegram bağlantısı şu anda kullanılamıyor. Lütfen daha sonra tekrar deneyin.</p>@endif
@endif
@else<p class="muted">Bağlamak istediğiniz tesisatı seçin.</p>@endif
</section>
<section class="card"><h2>E-posta gönderimi</h2><p class="muted">E-postalar sistemin ortak gönderim adresinden iletilir. Tesisat için yalnızca alıcı e-posta adresini girmeniz yeterlidir.</p></section><section class="card"><h2>Raporun kapsamı</h2><p class="muted">Günlük bildirim son hesaplanabilir günü; haftalık bildirim o günle biten son 7 günü inceler. Oranlar her sayaç için günlük endeks farklarıyla hesaplanır.</p><p class="muted">Bugünün verisi de sorgulanır. Rapor son alınabilen ölçüm gününü esas alır; gün kapanmamışsa son ölçüme kadar hesaplanır ve kısmi gün olarak belirtilir.</p><p class="muted">Endüktif %20, kapasitif %15 üzerindeyse uyarı verilir. Veri eksikse veya servise ulaşılamazsa “sorun yok” denmez.</p><p class="hint">Otomatik gönderim için sunucudaki zamanlayıcı çalışıyor olmalıdır. Kaydetmek test mesajı göndermez.</p></section></div></div>
<section class="card"><h2>Son gönderimler</h2><p class="muted">Her kanalın sonucu ayrı kaydedilir. Başarısız gönderimler 30 dakika arayla, aynı gün içinde en fazla 3 kez denenir.</p>
@if($history->isEmpty())<div class="empty">Henüz gönderim kaydı yok.</div>@else<div class="table-wrap"><table><thead><tr><th>TARİH</th><th>TESİSAT</th><th>KANAL</th><th>DURUM</th><th>AYRINTI</th></tr></thead><tbody>@foreach($history as $delivery)<tr><td>{{ $delivery->scheduled_date }}</td><td>{{ $rules->firstWhere('id', $delivery->rule_id)?->installation }}</td><td>{{ $delivery->channel === 'email' ? 'E-posta' : 'Telegram' }}</td><td><span class="status {{ $delivery->status === 'failed' ? 'error' : '' }}">{{ ['sent'=>'Gönderildi','failed'=>'Gönderilemedi','skipped'=>'Sorun yok · gönderim kapalı','pending'=>'Bekliyor','sending'=>'Gönderim başlatıldı / sonuç bekleniyor'][$delivery->status] ?? $delivery->status }}</span></td><td>{{ $delivery->error }}@if($delivery->body)<details><summary>Mesajı göster</summary><pre>{{ $delivery->body }}</pre></details>@endif</td></tr>@endforeach</tbody></table></div>@endif
</section>
@endsection
@push('scripts')
<script>
const frequency = document.getElementById('frequency');
if (frequency) { const updateWeekday = () => { document.getElementById('weekday-field').hidden = frequency.value !== 'weekly'; }; frequency.addEventListener('change', updateWeekday); updateWeekday(); }
</script>
@endpush
