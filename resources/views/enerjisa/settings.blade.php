@extends('enerjisa.layout')
@section('title', 'Erişim bilgileri')
@section('content')
<div class="heading"><div><div class="eyebrow">ENTEGRASYON</div><h1>Ölçüm servisi erişim bilgileri</h1><p class="muted">Firmanızın ölçüm servisi bağlantısını yönetin.</p></div></div>
@if(config('enerjisa.debug'))<div class="notice">Ölçüm servisi debug modu açık. Başarısız bağlantı testinin teknik ayrıntıları burada ve uygulama günlüğünde gösterilecek.</div>@endif
<div class="two-col"><section class="card"><h2>Servis hesabı</h2><p class="muted">Ölçüm servisi tarafından size iletilen erişim bilgilerini kullanın.</p>
<form method="post" action="{{ route('enerjisa.settings.save') }}" data-loading>@csrf @method('PUT')
    <div class="field"><label for="region">Veri kaynağı</label><input id="region" value="Ölçüm servisi" readonly><div class="hint">Yetkili olduğunuz ölçüm kaynağına bağlanılır.</div></div>
    <div class="field"><label for="client_id">MDM kullanıcı adı</label><input id="client_id" name="client_id" value="{{ old('client_id', $account->client_id) }}" required autocomplete="off" maxlength="255"></div>
    <div class="field"><label for="password">MDM parolası</label><input id="password" name="password" type="password" autocomplete="new-password" {{ $account->client_secret ? '' : 'required' }}><div class="hint">{{ $account->client_secret ? 'Parolanız kayıtlı. Değiştirmeyecekseniz boş bırakın.' : 'Erişim bilgileri veritabanında şifrelenerek saklanır.' }}</div></div>
    <button type="submit">Bilgileri kaydet</button>
</form></section>
<div><section class="card"><h2>Bağlantı durumu</h2><p class="muted">{{ $account->connected_at ? 'Son testte erişim jetonu başarıyla alındı.' : 'Kaydettiğiniz bilgilerin servis erişimini doğrulayın.' }}</p>
@if($account->connected_at)<p><span class="dot"></span>Son başarılı test: {{ $account->connected_at->format('d.m.Y H:i') }}</p>@endif
<form method="post" action="{{ route('enerjisa.connection') }}" data-loading>@csrf<button type="submit" class="secondary" @disabled(!$account->client_secret)>Bağlantıyı test et</button></form></section>
<section class="card"><h2>Başlamak için</h2><div class="steps"><div class="step"><span class="step-num">1</span><div><strong>Erişim bilgilerini kaydedin</strong><p>Panel giriş parolanızdan farklı olan MDM parolasını kullanın.</p></div></div><div class="step"><span class="step-num">2</span><div><strong>Bağlantıyı test edin</strong><p>Servisin hesabınıza erişim verdiğini doğrulayın.</p></div></div><div class="step"><span class="step-num">3</span><div><strong>Tesisatlarınızı getirin</strong><p>Yetkili tesisatları listeleyin ve sorgulamaya başlayın.</p></div></div></div></section></div></div>
@endsection
