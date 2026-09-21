> Bağımsız ReaktifRadar kurulum ve taşıma adımları için ../README.md ve ../deploy/CUTOVER.md esas alınır. Bu belge servis davranışını açıklar.

# Enerjisa deneme paneli

Mevcut Laravel uygulamasında `/enerjisa` altında bağımsız bir panel. Her yeni kayıt ayrı bir firma çalışma alanı açar. OkuKid personel ve veli hesaplarından ayrı `enerjisa` session guard'ı ve kullanıcı tablosu kullanılır. Bu ilk sürümde her firmanın bir sahibi vardır; ekip daveti, ödeme ve abonelik yönetimi kapsam dışıdır.

## Docker ile çalıştırma

Projenin mevcut `.env` ve `backend/.env` kurulumunu kullanır. Ana kurulum için kökteki README'yi izleyin. Yalnızca web uygulaması ve bağımlılıklarını başlatın:

```sh
docker compose up -d --build postgres redis app web
docker compose exec app composer install
docker compose exec app php artisan migrate --force
```

`backend/.env` içinde `APP_KEY` boşsa ilk kurulumda `docker compose exec app php artisan key:generate` çalıştırın. Mevcut anahtarı değiştirmeyin: MDM bilgileri ve sorgu yanıtları bu anahtarla şifrelenir. Veritabanı yedeğiyle birlikte anahtarı da güvenli saklayın.

Panel: `http://localhost:<APP_PORT>/enerjisa` (bu çalışma alanının mevcut portu **8088**). Manuel sorgular için worker gerekmez. Bildirimler için scheduler, merkezi SMTP ve/veya merkezi Telegram botu gerekir; `enerjisa:notify` her dakika zamanı gelen tesisat kurallarını kontrol eder. Redis mevcut uygulamanın oturum/önbellek altyapısı içindir. Harici JS/CSS, CDN veya Vite derlemesi gerekmez.

1. **Hesap oluşturun** ile firma adı, ad, e-posta ve panel parolasını girin.
2. **Erişim bilgileri** bölümünde Enerjisa'nın ilettiği Başkent MDM kullanıcı adı ve parolasını kaydedin.
3. **Bağlantıyı test et** ile token alınabildiğini doğrulayın.
4. **Tesisatlar → Tesisatları getir** ile yetkili tesisatları çekin.
5. **Veri sorgulama** bölümünde tesisat ve veri türünü seçin. Sorgular firma geçmişine kaydedilir.

Sabit/varsayılan giriş parolası veya gerçek Enerjisa kimlik bilgisi kaynak koduna eklenmez. Kayıt ekranı deneme için açıktır; e-posta doğrulaması ve parola sıfırlama e-postası bu sürüme dahil değildir.

## API sözleşmesi

Kaynak: kullanıcının sağladığı `Başkent-Servis Rehberi.pdf`, 7 sayfa. Belge teknik referans olarak kullanılmıştır. Örnek ekran görüntülerindeki kullanıcılar ve token'lar gerçek erişim bilgisi olarak kullanılmamıştır.

| İşlem | Metot / yol | Parametreler |
| --- | --- | --- |
| Token | POST `oauth/token` | Query: `grant_type=client_credentials`, `client_id`, `client_secret`, `consumerID=MDMAYPRD` |
| Tesisatlar | GET `customer/installation-list` | `access_token`, `consumerID=MDMAYPRD` |
| Saatlik tüketim/üretim | GET `customer/hourly-meter-information-multi-installation` | `access_token`, `consumerID`, `installationNumbers`, `meterMonth=Y-m`, `fromDate=Y-m-d H:i:s` (boş girişte ayın ilk günü) |
| Enerji/endeks | GET `customer/energy-value` | `access_token`, `consumerID`, `installationNumber`, `startDate=d/m/Y H:i:s`, `endDate=d/m/Y H:i:s`, `dataType` |

Ortak adres: `https://mdmsaatlik.baskentedas.com.tr/baskent/mdm-api/`. Header: `consumerID: MDM`. Belgenin saatlik servis URL metni AYEDAŞ, aynı sayfanın ekran görüntüsü Başkent adresini gösteriyor; girişteki “tesisatın kendi bölgesini kullanın” açıklaması uyarınca ilk sürüm tüm istekleri **Başkent** üzerinde tutar. Keyfi URL girişi ve bölgeler arası yönlendirme yoktur; HTTP yönlendirmeleri izlenmez.

`dataType=1` saatlik, `2` günlük endeks (en fazla bir ay), `3` reset (en fazla bir yıl). Tarihler İstanbul saat dilimindeki takvim tarihleri olarak gönderilir; başlangıç 00:00:00, bitiş 23:59:59. Bitiş günü dahil olduğundan sonraki ay/yıl sınırından önce olmalıdır. Gelecek günler, ters ve fazla uzun aralıklar reddedilir. Saatlik endekste bugün seçilebilir; bitiş saati mevcut İstanbul saatidir. Günlük ve reset sorguları en geç düne kadardır. Saatlik sorguda gelecekteki aylar ve seçilen ay dışındaki delta başlangıcı reddedilir. Tek tesisat sorgulama desteklenir; belgenin ayrıntılandırmadığı çoklu GET body biçimi uygulanmaz.

Her manuel sorgu yeni token alır. 401 durumunda token bir kez yenilenir; token kalıcı saklanmaz. Ağ ve HTTP hataları gizli URL/yanıt metni içermeyen mesajlara dönüştürülür. HTTP 200 içinde `Status`, `message_type` / `Message_type` kodları 1–6 da hata sayılır.

Belge tam bir JSON şeması sunmuyor. Tablo çıkarımı belgede görünen `installationNumber` (canlı serviste `instalationNumber`), `meterDate`, `meter_date` alanlarını özyinelemeli arar. Sayısal değerler ve alan adları değiştirilmez; bilinmeyen yapılar kaybolmaz, gizli alanlardan arındırılmış JSON bölümünde incelenebilir. Sonuç tabloları 100 kayıt/sayfa gösterir. İlk canlı servis yanıtıyla bu eşlemenin doğrulanması gerekir. Tesisat numarası elle de girilebilir; yetki kontrolünün son mercii, o firmanın kendi MDM hesabıyla çağrılan Enerjisa servisidir.

## İzolasyon ve taşıma

- Kod: `backend/app/Enerjisa`, `backend/routes/enerjisa.php`, `backend/resources/views/enerjisa`.
- Şema: `2026_09_17_000000_create_enerjisa_tables.php`; tablolar `enerjisa_accounts`, `enerjisa_users`, `enerjisa_queries`.
- Firma kimliği formdan alınmaz, oturumdaki kullanıcının hesabından çözülür. Tüm panel sorguları bu ilişki üzerinden yapılır; başka firmanın sonuç ID'si 404 döner.
- Parolalar hash'lenir; MDM kullanıcı adı/parolası ve sorgu payload'u Laravel encrypted cast ile saklanır. MDM parolası HTML'e geri yazılmaz ve validation session'ına flash edilmez. CSRF ve giriş/API hız sınırları aktiftir.
- Ayrı Laravel projesine taşırken yukarıdaki kod, şema, görünümler ve `config/auth.php` içindeki guard/provider yeterlidir. Taşınan şifreli veriler için aynı APP_KEY veya kontrollü yeniden şifreleme gerekir. OkuKid modüllerine bağımlılık yoktur.

## Doğrulama

```sh
docker compose exec app php artisan test --filter=EnerjisaPanelTest
docker compose exec app php artisan test --filter=PanelRoleTest
```

Testler ayrı `okukid_test` PostgreSQL veritabanını kullanır, dış HTTP isteklerini engeller. Kayıt/giriş/çıkış, firma izolasyonu, şifreleme, HTML kaçışları, tarih sınırları, API parametreleri, 401 yenileme ve güvenli hata gösterimi kapsanır. Gerçek hesap verilmediği için canlı MDM bağlantısı doğrulanmamıştır.

## Sonraki aşama

Günlük/haftalık bildirimler uygulanmıştır. Yük arttığında kuyruk işleyicisi ve normalize ölçüm deposuna geçilebilir. Hesaplar kullanıcının belirlediği eşiklere göre yapılır; mevzuata uygunluk kararı üretilmez.

## Bağlantı hata ayıklama

`backend/.env` dosyasında `ENERJISA_DEBUG=true` yapıp `docker compose exec app php artisan config:clear` çalıştırın. Erişim bilgileri ekranında **Bağlantıyı test et** işlemini tekrarlayın. Hata olduğunda tanı kartında referans numarası, yöntem, query içermeyen servis adresi, cURL hata kodu / HTTP durumu ve mevcut bağlantı süreleri gösterilir. Aynı bilgiler uygulamanın yapılandırılmış log kanalına `Enerjisa MDM diagnostic` başlığıyla yazılır. Tanı kartı yalnızca isteği yapan kullanıcının oturumunda bir sonraki sayfa için saklanır.

DNS (5/6), TCP (7), zaman aşımı (28), TLS (35/51/60) ve aktarım (52/55/56) hataları ayrıştırılır. Tanı ayrıca HTTP yanıtının bayt boyutunu, güvenli MIME türü sınıfını, JSON ayrıştırma hata kodunu ve empty/html/invalid_json/json_scalar/json_collection sınıfını gösterir. Ham exception, query parametreleri, ham header ve yanıt gövdeleri kaydedilmez. TLS doğrulaması açık kalır. Genel Laravel debug ayarından bağımsızdır. Kapatmak için `ENERJISA_DEBUG=false` ve `config:clear` kullanın.

## Sonuçları indirme

Başarılı sorgunun sonuç ekranındaki **CSV indir** ve **JSON indir** butonları, kaydedilmiş yanıtı indirir; servise yeniden istek atılmaz. CSV ekranda tanınan tüm tablo kayıtlarını (tüm sayfaları ve satırlardaki alanların birleşimini), JSON ise gizli alanlardan arındırılarak saklanmış servis yanıtının tamamını içerir. Tanınmayan veya boş yanıtlarda JSON indirilebilir; CSV sunulmaz. Başarısız/tamamlanmamış sorgular indirilemez. Dosya yalnızca sorgunun sahibi olan firma oturumuyla alınabilir.

CSV UTF-8 BOM ve noktalı virgül ayracı kullanır. Excel içe aktarımında tesisat/sayaç numaralarını metin seçerek baştaki sıfırları koruyun. Formül olarak yorumlanabilecek metinlerin başına güvenlik için tek tırnak eklenir; orijinal değerler JSON dosyasında korunur.

## Reaktif analiz

**Reaktif analiz** ekranı firmanın kayıtlı **Saatlik endeks** (`dataType=1`) sorgularını tesisat ve sayaç bazında birleştirir. **Hesapla** seçilen aralıkta daha önce başarıyla sorgulanmamış günleri otomatik getirir, sonra sonuç ekranına döner. Günlük/saatlik hesapların son sınırı için ertesi gün de sorgulanır; geçmiş günlerden ayrı olarak bugün 00:00 → mevcut saat aralığı da denenir. Servis bugünü kod 5 ile reddederse tarihsel verilerle devam edilir; diğer servis hataları gizlenmez. Bugünün kısmi sorgusu her hesaplamada yenilenir. Uzun aralıklar takvim aylarına bölünür. **Verileri yenile** kayıtlı aralığı yeniden sorgular; boş veya eksik servis yanıtları bu şekilde tekrar alınabilir. Tesisatlar açılır listeden seçilir; tesisat sahibi analiz ve sorgu sonuçlarında gösterilir. Saatlik tüketim/üretim yanıtları bu hesabın kaynağı değildir. Birden fazla sorguda aynı ölçüm varsa en yeni sorgu kullanılır; sayaçlar birbirine karıştırılmaz.

- Saatlik: tam saat ile bir sonraki tam saat karşılaştırılır; filtre en fazla 31 gün.
- Günlük: günün 00:00 endeksi ile ertesi günün 00:00 endeksi karşılaştırılır; son ölçüm gününde ertesi gece yarısı kaydı yoksa günün son mevcut ölçümü kullanılır ve kısmi gün olarak gösterilir. Ara günlerdeki eksik sınırlar bu şekilde doldurulmaz.
- Aylık: ilk gün 00:00 ile ay içindeki son mevcut ölçüm kullanılır. Devam eden ay veya geçmiş ayda son saat kaydı eksikse kısmi dönem etiketi gösterilir. Günlük/aylık filtre en fazla 366 gün; aylık görünüm seçilen tarihlerin ait olduğu ayları kapsar.

Endüktif oran `Δt_ri_kVarh / Δt_top_kWh × 100`, kapasitif oran `Δt_rc_kVarh / Δt_top_kWh × 100` olarak hesaplanır. Kapasitif formüldeki tekrar eden pay/payda yazım hatası kabul edilmiştir. Endüktif **%20 üzerinde**, kapasitif **%15 üzerinde** renkli uyarı gösterilir; eşitlik aşım değildir. Karar yuvarlanmamış farklarla verilir, ekranda üç ondalık gösterilir. Oranların ortalaması alınmaz.

Eksik sınır ölçümü, sıfır aktif fark, negatif/gerileyen endeks veya aynı sorguda çelişen ölçüm varsa oran hesaplanmaz. Dönem içindeki gözlenen endeks gerilemeleri de kontrol edilir. Saat dilimi İstanbul'dur. Filtreler, özet kartları, ilk/son endeks ayrıntıları ve tüm filtrelenmiş satırları içeren CSV indirme bulunur. Sayfa görüntüleme ve CSV indirme servis sorgusu başlatmaz; veri toplama CSRF korumalı Hesapla/Verileri yenile işlemleriyle yapılır. Servis hataları aynı analiz ekranında gösterilir, alınabilen sorgular korunur. E-posta gönderilmez.

Doğrulama: `docker compose exec app php artisan test --filter=Enerjisa`

## Tesisat bildirimleri

**Bildirimler** bölümünde e-posta için yalnızca tesisatın alıcı adresini girin. SMTP kullanıcıya sorulmaz; sistemin merkezi SMTP bağlantısı kullanılır. Telegram için tesisatı seçip “Telegram’ı bağla” düğmesine basın; bot sohbetinde Başlat deyin. Panele dönüp Telegram gönderimini açarak kaydedin. Sonra tesisatı seçerek bildirim durumunu, günlük/haftalık sıklığı, İstanbul saatine göre gönderim saatini, haftalık günü, yalnızca sorun olduğunda veya sorun yokken de gönder seçeneğini belirleyin. E-posta ve Telegram birlikte kullanılabilir. Alıcı e-posta ve bağlı Telegram hesabı her tesisat için ayrıdır. Kaydetmek test mesajı göndermez. Kurallar varsayılan olarak kapalıdır.

SMTP sunucu yöneticisi tarafından `backend/.env` içindeki `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME` (`smtp` veya `smtps`), `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` ve `MAIL_FROM_NAME` ile tanımlanır. Gönderim açıkça merkezi `smtp` mailer kullanır; `MAIL_MAILER=log` olsa bile mesaj log'a yazılıp gönderilmiş sayılmaz. Tenant hesabındaki eski SMTP değerleri dikkate alınmaz ve panelden güncellenemez. Ayar değişikliği sonrası `php artisan config:clear` ve scheduler yeniden başlatılmalıdır. Telegram kullanıcıya token/sohbet kimliği sormaz. Merkezi bot süper admin panelindeki Enerjisa → Genel durum ve Telegram ekranından token girilerek kurulur. Bot adı getMe ile bulunur, webhook otomatik kaydedilir ve secret otomatik oluşturulur. Panel ayarları şifreli veritabanında saklanır; ayarı değiştiren yönetici ve tarih tutulur. Panelde kayıt yoksa mevcut ENERJISA_TELEGRAM_* ortam ayarları kullanılmaya devam edilir. HTTPS APP_URL gereklidir. [Telegram bağlantı yöntemi](https://core.telegram.org/bots/features#deep-linking). Tek kullanımlık bağlantı 15 dakika geçerlidir, yalnızca hash'i saklanır. Webhook secret doğrulanmadan hiçbir bağlantı yapılmaz. Bu sürüm kişisel Telegram sohbetini destekler. Başlat bağlantıyı kurar; gönderim tercihleri panelden açılır. “Bağlantıyı kaldır” veya botta `/stop` bağlantıyı ve Telegram gönderimini kapatır. Eski kullanıcı botlarına ait bağlantılar geçiş migration'ında kapatılır, merkezi bota yeniden bağlanmalıdır. SMTP kimlik bilgileri yalnızca sunucu ortamındadır; bot token süper admin tarafından şifreli kaydedilir; eski tenant SMTP/bot ayarları kullanılmaz. Rapor gövdeleri şifrelidir.

Günlük rapor alınabilen son ölçüm gününü, haftalık rapor o günle biten son 7 günü inceler. Son kayıt yalnızca 00:00 ise önceki günü kapatır. Bugünün verisi ayrıca denenir; kod 5 ile reddedilirse mevcut son gün esas alınır. Son günün gece yarısı kapanışı beklenmez: 00:00 ile son ölçüm karşılaştırılır ve kısmi gün olduğu açıkça belirtilir. Örneğin son kayıt 21 Eylül 09:00 ise 21 Eylül 00:00 → 09:00 hesaplanır. Her çalışmada ilgili endeksler yenilenir. Her sayaç ayrı değerlendirilir; günlük oranların ortalaması alınmaz. Veri eksikse, aktif fark sıfırsa, endeks gerilemişse veya servis hatası varsa sağlıklı mesajı verilmez. Mesaj tesisat sahibini, dönemi, aşım sayısını ve ilk 8 sorunlu sayaç/günün oranlarını içerir.

Sunucuda kod güncellemesinden sonra:

```sh
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
docker compose up -d scheduler
```

Scheduler zaten çalışıyorsa kod güncellemesinin ardından `docker compose restart scheduler` kullanın. Kuyruk işleyicisi gerekmez; mevcut Laravel zamanlayıcı görevi arka planda çalıştırır. `php artisan schedule:list` ile kaydı kontrol edin. `php artisan enerjisa:notify` gerçek zamanı gelen açık kuralları gönderir; bağlantı testi veya dry-run değildir.

Her kural/tarih/kanal için tek kayıt oluşturulur. Başarılı gönderim tekrar edilmez; başarısız kanal aynı gün içinde 30 dakika arayla en fazla 3 kez denenir. Sağlıklı olup gönderilmemesi seçilen raporlar “Sorun yok · gönderim kapalı” olarak saklanır. Kapalı kurallar işlenmez. Geçmiş kaçırılan günler geriye dönük gönderilmez. Aynı gün kural değişikliği başarılı raporu tekrar göndermez. Gönderim sırasında süreç kapanırsa `sending` kaydı otomatik tekrar edilmez; geçmişten kontrol edilmelidir. Sağlayıcının isteği kabul edip yanıtın kaybolduğu ağ hatalarında uçtan uca tam bir kez teslim garantisi yoktur.

Canlı SMTP/Telegram kimlik bilgileri olmadan testler sahte taşıyıcılarla çalışır; gerçek teslimat ayrıca doğrulanmalıdır.

## Süper admin yönetimi

Mevcut yönetim panelinde (`/panel`, `PANEL_PATH` özelleştirilmişse o adres) **Enerjisa** menüsü yalnızca doğrulanmış `super_admin` personeline açıktır. Enerjisa müşteri girişi bu yetkiyi sağlamaz.

- **Enerjisa Kullanıcıları:** arama, firma, açık/kapalı filtre, son giriş, kayıt tarihi; kullanıcı ekleme, bilgi/parola güncelleme ve hesabı kapatma/açma. Kullanıcı başka firmaya taşınmaz. Parola boş bırakılırsa korunur.
- **Enerjisa Firmaları:** firma oluşturma/düzenleme, kullanıcı/sorgu sayısı, son MDM bağlantı testi ve firma kapatma/açma. Kapalı firmanın tüm kullanıcı girişleri ve otomatik bildirimleri engellenir. Kalıcı silme sunulmaz.
- **Genel durum ve Telegram:** kullanıcı/firma/bildirim sayaçları, son 24 saat gönderimleri, merkezi SMTP durum bilgisi (teslimat testi değildir), zamanlayıcı son kontrolü, son servis/bildirim hataları. SMTP düzenlenmez; mevcut `.env` kullanılır. Telegram kurulumu bu ekrandan yapılır; BotFather token'ı girilip “Doğrula ve Telegram’ı etkinleştir” tıklanır. Canlı HTTPS adresi gerekir. Farklı bota geçiş eski Telegram bağlantılarını kapatır; kullanıcı yeniden bağlanır.

İlk süper admin yoksa sunucuda `docker compose exec app php artisan okukid:create-admin` çalıştırın. Komut ad/e-posta ve parolayı sorar; varsayılan rol süper admindir. Parola terminal geçmişine yazılmaz. Var olan süper admin hesabıyla giriş yapılabilir; kullanıcıya ait müşteri hesabı otomatik yükseltilmez. Kod güncellemesinde migration ve scheduler yeniden başlatma adımlarını uygulayın.

## ReaktifRadar e-posta tasarımı

Bildirim e-postaları HTML tasarım ve düz metin alternatifi birlikte gönderilir. Konu “ReaktifRadar · Tesisat … · Durum raporu” biçimindedir. Tesisat sahibi, dönem, durum özeti, sayaç/gün sayısı, eşik aşımı sayısı ve oran tablosu gösterilir. Normal, eşik aşımı, eksik veri ve servis hatası ayrı renk/metinlerle belirtilir. En fazla 12 satır, sorunlu dönemler öncelikli gösterilir; tüm sonuçlar için tesisat/tarih filtresi taşıyan panel bağlantısı bulunur. Telegram metni değişmez.

E-posta verisi raporla birlikte şifreli kaydedilir; tekrar gönderimde aynı rapor kullanılır. Eski, yalnızca metin içeren bekleyen kayıtlar da yeni HTML çerçevesinde güvenli biçimde gösterilir. Harici görsel/font/JavaScript kullanılmaz. HTML kaçışları ve multipart/alternative çıktısı otomatik testlerle doğrulanmıştır; gerçek e-posta istemcilerindeki görünüm için canlı teslimat testi ayrıca yapılmalıdır.
