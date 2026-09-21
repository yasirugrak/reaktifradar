@extends('enerjisa.layout')
@section('title', $register ? 'Hesap oluştur' : 'Giriş')
@section('content')
<div class="auth-page">
    <div class="auth-intro">
        <div><div class="brand"><b>ϟ</b> ReaktifRadar</div><div class="caption">ENERJİ VERİ YÖNETİMİ</div></div>
        <div><h1>Enerji veriniz.<br>Tek bir yerde.</h1><p>Tesisatlarınızı görüntüleyin, tüketim ve üretim verilerinizi sorgulayın. Başkent EDAŞ MDM servislerine bağlı çalışma alanınız.</p></div>
        <div class="caption">BAŞKENT EDAŞ · MDM ENTEGRASYONU</div>
    </div>
    <main class="auth-content"><div class="auth-form">
        <div class="eyebrow">{{ $register ? 'İLK ADIM' : 'ÇALIŞMA ALANINIZ' }}</div>
        <h1>{{ $register ? 'Hesabınızı oluşturun' : 'Tekrar hoş geldiniz' }}</h1>
        <p class="muted">{{ $register ? 'Firmanız için ayrı bir alanla başlayın.' : 'Enerji verilerinize ulaşmak için giriş yapın.' }}</p>
        @include('enerjisa.messages')
        <form method="post" action="{{ route($register ? 'enerjisa.register' : 'enerjisa.login') }}" data-loading>
            @csrf
            @if($register)
            <div class="field"><label for="company">Firma adı</label><input id="company" name="company" value="{{ old('company') }}" required maxlength="160" autocomplete="organization"></div>
            <div class="field"><label for="name">Ad soyad</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="160" autocomplete="name"></div>
            @endif
            <div class="field"><label for="email">E-posta</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username"></div>
            <div class="field"><label for="password">Panel parolası</label><input id="password" name="password" type="password" required autocomplete="{{ $register ? 'new-password' : 'current-password' }}">@if($register)<div class="hint">En az 6 karakter; harf ve rakam içermeli.</div>@endif</div>
            @if($register)<div class="field"><label for="confirmation">Parola tekrarı</label><input id="confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>@endif
            <button type="submit" class="button">{{ $register ? 'Çalışma alanı oluştur →' : 'Giriş yap →' }}</button>
        </form>
        <p class="auth-note">@if($register)Hesabınız var mı? <a href="{{ route('enerjisa.login') }}">Giriş yapın</a>@else İlk kez mi kullanıyorsunuz? <a href="{{ route('enerjisa.register') }}">Hesap oluşturun</a>@endif</p>
        <p class="auth-note">Panel hesabınız ve Enerjisa servis hesabınız ayrıdır.</p>
    </div></main>
</div>
@endsection
