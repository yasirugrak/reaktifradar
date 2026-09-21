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

Güncelleme sonrası `php artisan migrate --force` ve `php artisan optimize:clear` çalıştırın. Zamanlayıcıyı yeniden başlatın. Aranma talepleri SMTP gerektirmeden veritabanına kaydedilir; `/admin` içindeki **Aranma Talepleri** ekranından yeni/görüşüldü/tamamlandı olarak yönetilir.

Eski `/enerjisa` GET bağlantıları sorgu parametreleri korunarak `/panel` adresine yönlenir. Yeni Telegram webhook adresi `/panel/telegram/webhook`; mevcut bot bağlantısının kesilmemesi için eski webhook da çalışır.
