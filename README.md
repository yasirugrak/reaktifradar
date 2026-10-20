# ReaktifRadar

Reaktif enerji takibi, tesisat yönetimi ve e-posta/Telegram raporları. Bağımsız Laravel 13 / PHP 8.4 / Filament 5 uygulaması; OkuKid kodunu veya vendor dizinini kullanmaz.

- Canlı adres: `https://reaktifradar.com` (kök adres pazarlama sayfasıdır).
- Müşteri paneli: `/panel`; sistem yönetimi: `/admin`.
- Mevcut PostgreSQL 16 servisi kullanılır; veritabanı `reaktifradar` olur.
- Ayrı uygulama anahtarı, oturum çerezi ve Redis anahtar öneki ve Redis database numaraları kullanılır. PostgreSQL ve Redis servisleri OkuKid ile ortaktır. Kuyruk işleri şu an senkron yürür.
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
3. `php artisan queue:restart` ile mevcut işçileri yeniden başlatın. `php artisan queue:work database --queue=default --timeout=60 --tries=1` işçisini sürekli çalıştırın (Supervisor veya container).

İl/bölge, OSB adı ve sektörle Google Custom Search JSON API ile web araması yapılır. Sonuç başlıkları firma adayıdır; firma büyüklüğü ve OSB üyeliği manuel doğrulanmalıdır. En fazla 60 aday site, her sitede en fazla iki sayfa okunur. Genel kurumsal adresler alınır; kişisel adresler tahmin edilmez. Aynı taramadaki mükerrer e-postalar tekilleştirilir. Taramalar arasında aynı adres tekrar bulunabilir.

Liste onayı gönderim izni kaydı değildir. Bu modül e-posta göndermez; yalnızca inceleme ve onaylı kayıtların CSV aktarımını sağlar. Kaynak, tarih ve hariç tutma durumu saklanır. Hariç tutma tarama kapsamındadır; kampanya sistemi eklenirken global ret listesi uygulanmalıdır.

JavaScript ile oluşturulan, CAPTCHA isteyen veya erişilemeyen sayfalar desteklenmez. Hata sayısı arayüzde görünür. Özel ağ hedefleri engellenir, DNS sonucu bağlantıya sabitlenir, yönlendirmeler yeniden kontrol edilir.

Google ayarları: [Custom Search JSON API](https://console.cloud.google.com/apis/library/customsearch.googleapis.com) için API anahtarı ve [Programmable Search Engine](https://programmablesearchengine.google.com/controlpanel/all) panelinden arama motoru kimliği (cx) alın. Sonuçlar arama motorunun yapılandırılmış kapsamıyla sınırlıdır. API erişimi olan bir Google projesi gerekir; anahtar oluşturmak tek başına erişim sağlamaz. 20/40/60 aday için en fazla 2/4/6 sorgu yapılır; tekrar eden alan adları nedeniyle aday sayısı daha düşük olabilir.
