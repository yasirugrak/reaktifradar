<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ReaktifRadar — Ölçümden müdahaleye, mühendislik takibi</title>
    <meta name="description" content="Tesisinizin reaktif enerji değerlerini mühendisimizle takip ediyoruz. Günlük rapor, eşik aşımlarında ek uyarı ve arıza durumunda müdahale planlaması için bizimle görüşün.">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v=service-2">
</head>
<body>
@php
    $phone = trim((string) config('contact.phone'));
    $dial = preg_replace('/[^+0-9]/', '', $phone);
    $email = trim((string) config('contact.email'));
@endphp
<a class="skip" href="#icerik">İçeriğe geç</a>
<header class="wrap header">
    <a class="brand" href="{{ route('home') }}" aria-label="ReaktifRadar ana sayfa"><span class="brand-mark" aria-hidden="true">↗</span>Reaktif<span>Radar</span></a>
    <nav aria-label="Ana menü"><a href="#hizmet">Hizmetimiz</a><a href="#surec">Nasıl çalışır?</a><a href="#sorular">Sorularınız</a></nav>
    <a class="login" href="{{ route('enerjisa.login') }}">Müşteri girişi <span aria-hidden="true">↗</span></a>
</header>
<main id="icerik">
<section class="wrap hero">
    <div class="hero-copy">
        <p class="eyebrow"><span></span> REAKTİF ENERJİ TAKİP HİZMETİ</p>
        <h1>Değerleri izleriz.<br>Riski bildiririz.<br><em>Yanınızda oluruz.</em></h1>
        <p class="lead">Tesisinizin reaktif enerji takibini mühendisimize bırakın. Verilerinizi inceleyelim, günlük raporunuzu sunalım; sorunlarda sizi uyarıp müdahaleyi birlikte planlayalım.</p>
        <div class="actions"><a class="button primary" href="#iletisim">Tesisim için görüşelim <span aria-hidden="true">↗</span></a>@if($phone)<a class="text-link" href="tel:{{ $dial }}">{{ $phone }}</a>@else<a class="text-link" href="#surec">Hizmeti keşfedin ↓</a>@endif</div>
        <p class="hero-note">Yetkilendirilmiş veri erişimi. Mühendis değerlendirmesi.<br>İşletmenize uygun takip ve müdahale planı.</p>
    </div>
    <div class="report-scene" aria-label="Örnek günlük rapor görünümü">
        <div class="scene-top"><span>TESİSİNİZDEN BİR GÜNLÜK BAKIŞ</span><span class="sample">Örnek rapor</span></div>
        <article class="report-card">
            <div class="report-heading"><div><p class="eyebrow">REAKTİFRADAR / GÜNLÜK ÖZET</p><h2>Üretim tesisi</h2></div><span class="report-icon" aria-hidden="true">↗</span></div>
            <div class="report-status"><span class="dot"></span> Takip sürüyor <span>Son ölçüm · 18.00</span></div>
            <div class="metrics"><div><p>Endüktif oran</p><strong>%22,6</strong><span class="metric-alert">↑ Eşik üzerinde</span></div><div><p>Kapasitif oran</p><strong>%8,2</strong><span class="metric-ok">✓ Eşik içinde</span></div></div>
            <div class="chart-label"><span>Gün içindeki endüktif oran</span><span>Örnek ölçümler</span></div>
            <svg class="chart" viewBox="0 0 420 120" role="img" aria-label="Örnek endüktif oranın gün sonunda eşik üzerine çıktığı grafik"><defs><linearGradient id="fill" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#bfe6c7" stop-opacity=".65"/><stop offset="1" stop-color="#bfe6c7" stop-opacity="0"/></linearGradient></defs><path d="M0 94L35 85L70 91L105 70L140 77L175 62L210 70L245 48L280 54L315 35L350 40L385 22L420 17V120H0Z" fill="url(#fill)"/><path d="M0 94L35 85L70 91L105 70L140 77L175 62L210 70L245 48L280 54L315 35L350 40L385 22L420 17" fill="none" stroke="#276952" stroke-width="3"/><path d="M0 38H420" stroke="#b55835" stroke-dasharray="5 5"/><text x="4" y="30" fill="#9d422a" font-size="12">İzleme eşiği</text></svg>
            <div class="chart-axis"><span>00.00</span><span>09.00</span><span>18.00</span></div>
            <div class="engineer-note"><span aria-hidden="true">!</span><div><strong>Ek uyarı · Endüktif oran yükseldi</strong><p>Kompanzasyon sisteminin kontrolü için mühendis değerlendirmesi ve müdahale planı.</p></div></div>
        </article>
        <div class="report-bottom"><span aria-hidden="true">↳</span><p><strong>Veriden anlaşılır aksiyona.</strong><br>Ne olduğunu ve sıradaki adımı birlikte görelim.</p></div>
        <p class="sample-note">Görseldeki tesis, ölçümler ve değerlendirme örnektir.</p>
    </div>
</section>
<div class="promise-bar"><div class="wrap"><span>01 <strong>Mühendis takibi</strong></span><span>02 <strong>Günlük raporlama</strong></span><span>03 <strong>Sorunlarda ek uyarı</strong></span><span>04 <strong>Müdahale planlaması</strong></span></div></div>
<section id="hizmet" class="wrap section">
    <div class="section-heading"><div><p class="eyebrow">BİR PANELDEN DAHA FAZLASI</p><h2>Takibin arkasında<br>bir mühendis var.</h2></div><p>Grafikleri tek başınıza yorumlamanız gerekmez. Ölçümleri anlamlandırıyor, dikkatinizi gerektiren durumları açıkça paylaşıyoruz.</p></div>
    <div class="service-grid">
        <article><span class="service-number">01 / TAKİP</span><h3>Tesisinize düzenli bakış</h3><p>Yetkilendirdiğiniz ölçüm verileri üzerinden endüktif ve kapasitif oranları takip eder, günlük değişimleri mühendisimizle değerlendiririz.</p><div class="card-foot">Ölçüm → İnceleme → Değerlendirme</div></article>
        <article><span class="service-number">02 / BİLGİLENDİRME</span><h3>Her gün anlaşılır rapor</h3><p>Sorun olmasa da tesisinizin durumunu bilin. Günlük raporlarla mevcut tabloyu, eşik aşımlarında ise ek uyarıları e-posta ve Telegram üzerinden paylaşırız.</p><div class="card-foot">Günlük özet + Sorunlarda ek uyarı</div></article>
        <article><span class="service-number">03 / MÜDAHALE</span><h3>Sorunda birlikte hareket</h3><p>Arıza veya uygunsuz değer tespitinde sizinle iletişime geçer, gerekli kontrol ve müdahaleyi hizmet kapsamınıza göre planlarız.</p><div class="card-foot">İletişim → Kontrol → Müdahale</div></article>
    </div>
</section>
<section id="surec" class="workflow"><div class="wrap section">
    <div class="section-heading"><div><p class="eyebrow">BAŞLAMAK İÇİN</p><h2>Önce tesisinizi tanıyalım.</h2></div><a class="button light" href="#iletisim">Beni arayın ↗</a></div>
    <div class="steps"><article><span>1</span><h3>İhtiyacınızı konuşalım</h3><p>Tesisinizi, mevcut kompanzasyon yapınızı ve takip beklentinizi birlikte değerlendirelim.</p></article><article><span>2</span><h3>Erişimi birlikte tanımlayalım</h3><p>Gerekli veri erişim yetkisini sizin onayınızla oluşturalım; takip ve iletişim planını netleştirelim.</p></article><article><span>3</span><h3>Takibi başlatalım</h3><p>Günlük raporlarınızı sunalım. Sorunlarda ek uyarı ve mühendis değerlendirmesiyle sonraki adımı belirleyelim.</p></article></div>
    <p class="workflow-note">Veri güncelliği ölçüm kaynağına bağlıdır. Eksik veya geciken ölçümleri raporda belirtiriz; eksik veriyi “sorun yok” olarak değerlendirmeyiz.</p>
</div></section>
<section class="wrap section faq" id="sorular"><div><p class="eyebrow">AKLINIZDAKİLER</p><h2>Net bilgi.<br>Birlikte belirlenen<br>hizmet kapsamı.</h2></div><div>
    <details open><summary>Bu hizmet yalnızca bir yazılım mı?</summary><p>Hayır. Panel, takip hizmetimizin bir parçasıdır. Yetki verdiğiniz tesis verilerini mühendisimizle değerlendirir; raporlama, uyarı ve gerektiğinde müdahale sürecini birlikte yürütürüz.</p></details>
    <details><summary>Sorun olmasa da rapor alacak mıyım?</summary><p>Evet. Günlük raporda mevcut durumu paylaşırız. Sorun tespit edildiğinde ek uyarı yaparız. İletişim kanallarını ve takip planını hizmet başlangıcında birlikte belirleriz.</p></details>
    <details><summary>Arıza durumunda nasıl ilerliyoruz?</summary><p>Önce ölçümleri ve sorunun niteliğini değerlendirip sizinle iletişime geçeriz. Uzaktan kontrol veya yerinde müdahalenin kapsamı, süresi ve varsa ek bedeli tesisiniz için belirlenen hizmet koşullarına göre netleştirilir.</p></details>
    <details><summary>Hizmet bedeli nasıl belirleniyor?</summary><p>Tesis sayısı, takip ihtiyacı ve müdahale kapsamına göre teklif hazırlıyoruz. İletişim bilgilerinizi bırakın; tesisinize uygun hizmeti görüşelim.</p></details>
</div></section>
<section id="iletisim" class="contact-section"><div class="wrap contact-grid">
    <div><p class="eyebrow">TANIŞALIM</p><h2>Tesisinizin takibini<br>birlikte planlayalım.</h2><p class="contact-lead">Bizi arayın, e-posta gönderin veya numaranızı bırakın. İhtiyacınızı dinleyip size uygun hizmeti konuşalım.</p>
        <div class="contact-links">@if($phone)<a href="tel:{{ $dial }}"><span>TELEFON</span><strong>{{ $phone }} ↗</strong></a>@endif @if($email)<a href="mailto:{{ $email }}"><span>E-POSTA</span><strong>{{ $email }} ↗</strong></a>@endif</div>
        <p class="contact-note">İlk görüşmede veri erişimini, raporlama düzenini ve müdahale kapsamını netleştiriyoruz.</p>
    </div>
    <div class="callback-card"><h3>Sizi arayalım.</h3><p>Kısa bir tanışma, doğru bir başlangıç.</p>
        @if(session('callback_success'))<div class="success" role="status">{{ session('callback_success') }}</div>@endif
        @if($errors->any())<div class="errors" role="alert"><strong>Bilgilerinizi kontrol edin.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form action="{{ route('callback.store') }}" method="POST">@csrf
            <label for="name">Adınız ve soyadınız</label><input id="name" name="name" autocomplete="name" maxlength="100" value="{{ old('name') }}" required>
            <label for="phone">Telefon numaranız</label><input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="05XX XXX XX XX" maxlength="30" value="{{ old('phone') }}" required>
            <label for="company">İşletme adı <span>(isteğe bağlı)</span></label><input id="company" name="company" autocomplete="organization" maxlength="150" value="{{ old('company') }}">
            <div class="honeypot" aria-hidden="true"><label for="website">Web sitesi</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <label class="consent"><input type="checkbox" name="contact_permission" value="1" required @checked(old('contact_permission'))><span>Hizmet talebim hakkında benimle iletişime geçilmesini kabul ediyorum.</span></label>
            <p class="privacy-note">Bu formdaki bilgiler, talebinizi değerlendirmek ve sizinle iletişim kurmak için ReaktifRadar ekibine iletilir.</p>
            <button class="button primary" type="submit">Beni arayın <span aria-hidden="true">↗</span></button>
        </form>
    </div>
</div></section>
</main>
<footer class="wrap footer"><a class="brand" href="{{ route('home') }}">Reaktif<span>Radar</span></a><p>Ölçümden müdahaleye, mühendislik takibi.</p><span>© {{ date('Y') }} ReaktifRadar</span></footer>
</body></html>
