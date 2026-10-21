# ReaktifRadar

Reaktif enerji takibi, tesisat yönetimi ve e-posta/Telegram raporları. Bağımsız Laravel 13 / PHP 8.4 / Filament 5 uygulaması; OkuKid kodunu veya vendor dizinini kullanmaz.

- Canlı adres: `https://reaktifradar.com` (kök adres pazarlama sayfasıdır).
- Müşteri paneli: `/panel`; sistem yönetimi: `/admin`.
- Mevcut PostgreSQL 16 servisi kullanılır; veritabanı `reaktifradar` olur.
- Ayrı uygulama anahtarı, oturum çerezi ve Redis anahtar öneki ve Redis database numaraları kullanılır. PostgreSQL ve Redis servisleri OkuKid ile ortaktır. Varsayılan işler senkron yürür; firma keşfi database kuyruğunda ayrı worker servisiyle çalışır.
- Bildirim servisi ilk kurulumda kapalıdır; taşınma tamamlanmadan açılmaz.
- Enerjisa servisinin kendi adı ve uyumluluk için mevcut rota/tablo adları korunmuştur.

## Canlı kurulum ve veri taşıma

`deploy/CUTOVER.md` adımlarını izleyin. Docker dosyası ikinci PostgreSQL başlatmaz ve dışarı port açmaz. Mevcut `okukid_internal` ve `yasir_net` ağlarına katılır. OkuKid compose projesi önce başlatılmalıdır. `.env` içinde ağ adları, `APP_UID`, `APP_GID` gerekirse özelleştirilebilir.

```sh
cp .env.example .env
# .env değerlerini tamamlayın. APP_KEY eski uygulamadan kopyalanmaz.
docker compose build
docker compose run --rm app composer install --no-dev --prefer-dist --no-interaction
docker compose run --rm app php artisan key:generate --force
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan filament:assets
```

`.env` ve `storage` için sunucu kullanıcısına uygun yazma izinleri verin. Nginx de `/var/www/html/reaktifradar/public` yolunu salt okunur görebilmeli. `.env.example` SMTP değerlerini içermez; mevcut merkezi SMTP ayarlarını yeni `.env` dosyasına alın. Kullanıcılara SMTP ayarı sorulmaz.

## Taşıma komutu

```sh
# Önce yalnızca okur ve şifre çözme kontrolü yapar:
php artisan reaktifradar:import
# Boş hedefe kopyalar, içerikleri ve kayıt sayılarını doğrular:
php artisan reaktifradar:import --apply
```

Kaynak bağlantı `LEGACY_DB_*`, kaynak şifreleme anahtarı `LEGACY_APP_KEY` ile verilir. Kaynak geçmiş anahtarları varsa `LEGACY_APP_PREVIOUS_KEYS` içinde virgülle ayrılır. Yeni anahtar `APP_KEY` olur. Kaynakta güncel Enerjisa şeması bulunmalıdır.

Yalnızca `enerjisa_*` tabloları ve `super_admin` rolündeki yönetici hesapları taşınır. OkuKid veli/çocuk verileri taşınmaz. ID'ler, parola hash'leri ve bildirim gönderim durumları korunur. Diğer yöneticilere ait `updated_by` referansları boşaltılır. Şifreli alanlar yeni anahtarla tekrar şifrelenir. Hedef doluysa işlem durur; üzerine yazma/silme yapılmaz. Kaynak bağlantıda salt okunur, tutarlı bir PostgreSQL anlık görünümü kullanılır. Hata halinde hedef transaction geri alınır. Kaynak verileri silinmez.

## Geliştirme ve test

PHP 8.4, Composer ve PostgreSQL gerekir. Yerel `.env` için `APP_URL=http://127.0.0.1:8098`, `SESSION_SECURE_COOKIE=false`, `DB_HOST=127.0.0.1`, uygun port ve `MAIL_MAILER=log`, `CACHE_STORE=database` kullanın. Bildirimleri kapalı tutun.

```sh
composer install
php artisan serve --host=127.0.0.1 --port=8098
php artisan test --compact
vendor/bin/phpstan analyse --memory-limit=1G
```

Testler yalnızca `reaktifradar_test` ve `reaktifradar_import_test` veritabanlarını kullanır. Test bağlantısı kullanıcısının bu iki database üzerinde şema oluşturma yetkisi gerekir. `LegacyImportTest` ikinci test database'ini her testte sıfırlar; canlı veya geliştirme veritabanı adı kullanmayın.

## Hizmet sayfası ve aranma talepleri

Ana sayfadaki telefon için `CONTACT_PHONE`, e-posta için `CONTACT_EMAIL` tanımlayın. Telefon boşsa telefon bağlantısı gösterilmez; e-posta tanımlı değilse `MAIL_FROM_ADDRESS` kullanılır.

Güncelleme sonrası `php artisan migrate --force` ve `php artisan optimize:clear` çalıştırın. Zamanlayıcıyı yeniden başlatın. Aranma talepleri önce veritabanına kaydedilir, sonra merkezi SMTP ile `CALLBACK_NOTIFICATION_EMAIL` (boşsa `CONTACT_EMAIL`) adresine HTML bildirim gönderilir. SMTP hatasında kayıt korunur ve kayıt kimliği uygulama günlüğüne yazılır; `/admin` içindeki **Aranma Talepleri** ekranından yeni/görüşüldü/tamamlandı olarak yönetilir.

Eski `/enerjisa` GET bağlantıları sorgu parametreleri korunarak `/panel` adresine yönlenir. Yeni Telegram webhook adresi `/panel/telegram/webhook`; mevcut bot bağlantısının kesilmemesi için eski webhook da çalışır.

## Firma e-posta keşfi

`/admin/prospecting` yalnızca doğrulanmış süper yöneticilere açıktır.

1. `php artisan migrate --force` ile tabloları oluşturun.
2. `.env` içine `GOOGLE_SEARCH_API_KEY` ve `GOOGLE_SEARCH_ENGINE_ID` (cx) ekleyin, config cache kullanılıyorsa `php artisan config:cache` çalıştırın.
3. Docker kurulumunda `docker compose up -d worker` ile kalıcı kuyruk işçisini başlatın. Servis yeniden başlatmalarda otomatik açılır. Docker kullanılmıyorsa `php artisan queue:work database --sleep=2 --timeout=60 --tries=1` komutunu Supervisor ile sürekli çalıştırın. Kuyruk adı `DB_QUEUE` ayarından okunur (varsayılan: `default`).
4. Kod veya API ayarı değiştiğinde `docker compose exec app php artisan config:cache` ve `docker compose restart worker` çalıştırın.

Tarama “Tarama işçisi bekleniyor” durumunda kalırsa `docker compose ps worker` ve `docker compose logs --tail=50 worker` ile işçiyi kontrol edin. `queue:restart` tek başına yeni bir işçi başlatmaz. İşçi açılınca kuyrukta bekleyen taramalar otomatik işlenir; aynı taramayı tekrar oluşturmanız gerekmez.

İl/bölge, OSB adı ve sektörle Google Custom Search JSON API ile web araması yapılır. Sonuç başlıkları firma adayıdır; firma büyüklüğü ve OSB üyeliği manuel doğrulanmalıdır. En fazla 60 aday site, her sitede en fazla iki sayfa okunur. Genel kurumsal adresler alınır; kişisel adresler tahmin edilmez. Aynı taramadaki mükerrer e-postalar tekilleştirilir. Taramalar arasında aynı adres tekrar bulunabilir.

Liste onayı gönderim izni kaydı değildir. Modül inceleme, onaylı kayıtların CSV aktarımı ve ayrıca onaylanan toplu tanıtım gönderimini sağlar. Kaynak, tarih ve hariç tutma durumu saklanır. Toplu gönderimde herhangi bir taramada hariç tutulan adresler de dışarıda bırakılır.

JavaScript ile oluşturulan, CAPTCHA isteyen veya erişilemeyen sayfalar desteklenmez. Hata sayısı arayüzde görünür. Özel ağ hedefleri engellenir, DNS sonucu bağlantıya sabitlenir, yönlendirmeler yeniden kontrol edilir.

Google ayarları: [Custom Search JSON API](https://console.cloud.google.com/apis/library/customsearch.googleapis.com) için API anahtarı ve [Programmable Search Engine](https://programmablesearchengine.google.com/controlpanel/all) panelinden arama motoru kimliği (cx) alın. Sonuçlar arama motorunun yapılandırılmış kapsamıyla sınırlıdır. API erişimi olan bir Google projesi gerekir; anahtar oluşturmak tek başına erişim sağlamaz. 20/40/60 aday için en fazla 2/4/6 sorgu yapılır; tekrar eden alan adları nedeniyle aday sayısı daha düşük olabilir.

### Serper ile tarama

`.env` dosyasına `SERPER_API_KEY` ekleyin. `php artisan migrate --force` ile tarama servis alanını ekleyin; ardından config önbelleğini yenileyin ve worker servisini yeniden başlatın. Tarama oluştururken **Arama servisi → Serper** seçin. Google için mevcut iki ayar kullanılmaya devam eder. Servis tercihi her taramada saklanır; eski kayıtlar Google olarak kalır ve yeniden başlatma kayıtlı servisi kullanır. Farklı servisle denemek için formdan yeni tarama oluşturun. Serper için Google anahtarı veya cx gerekmez.

### Tanıtım e-postası gönderimi

`/admin/introduction-mail` ekranından önizleme ve kendi yönetici adresinize test gönderimi yapılır. Firma keşfinde **Onaylananlara mail gönder** butonu seçili taramanın tüm sayfalarındaki uygun onaylı adresleri onay sonrası database kuyruğuna alır. Her alıcı ayrı SMTP iletisi alır. Bu tanıtım aynı e-posta adresine taramalar arasında da yalnızca bir kez kuyruğa alınır. Gönderim kaydı tarama silinse bile korunur. Kuyruktayken onayı kaldırılan, silinen veya herhangi bir taramada hariç tutulan alıcı gönderilmez. SMTP hatası/zaman aşımında otomatik tekrar yapılmaz; teslim durumu belirsiz olabileceğinden posta sunucusu kayıtları incelenmelidir. “Posta sunucusuna iletildi” alıcının gelen kutusuna teslim edildiği anlamına gelmez.

Yeni `prospect_mail_deliveries` tablosu için migration çalıştırın ve worker servisini yeniden başlatın. Gönderim merkezi SMTP ayarlarını kullanır.

### Bağlantıdan e-posta toplama

Firma keşfinde **Web sitesi bağlantısı** yöntemini seçip HTTP/HTTPS adresi girin. API anahtarı gerekmez; database worker gerekir. Aynı alan adındaki en fazla 20 sayfa, iletişim bağlantılarına öncelik verilerek taranır. Sayfalarda yayımlanmış geçerli e-posta adresleri toplanır (firma aramasındaki ortak posta kutusu filtresi bu modda uygulanmaz). Kaynak URL kaydedilir; mevcut onaylama, CSV ve gönderim akışı kullanılabilir. Sorgu parametreli iç bağlantılar, dosya indirmeleri ve başka alan adları takip edilmez. JavaScript/OCR desteği yoktur. Yeni source_url alanı için migration çalıştırın; ardından worker servisini yeniden başlatın.
