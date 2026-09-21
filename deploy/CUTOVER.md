# reaktifradar.com geçişi

Bu dosya kurulum tarifidir; DNS/TLS ve canlı veri aktarımının yapılmış olduğunu göstermez.

## 1. Hazırlık

- Yeni bağımsız projeyi sunucuda `/home/reaktifradar` gibi ayrı dizine alın.
- Var olan `okukid-postgres` PostgreSQL servisini kullanın. `egitim-platform` ağında ulaşılabilir olmalı. `compose.yml` yeni DB servisi açmaz.
- PostgreSQL yöneticisiyle `reaktifradar` rolünü ve database'ini oluşturun. Örnek, etkileşimli `psql` oturumunda:

```sql
CREATE ROLE reaktifradar LOGIN;
\password reaktifradar
CREATE DATABASE reaktifradar OWNER reaktifradar;
REVOKE ALL ON DATABASE reaktifradar FROM PUBLIC;
GRANT CONNECT ON DATABASE reaktifradar TO reaktifradar;
```

- `.env.example` → `.env`: `DB_DATABASE=reaktifradar`, `DB_USERNAME=reaktifradar`, yeni parola. `APP_URL=https://reaktifradar.com`, `APP_ENV=production`, `APP_DEBUG=false`. Yeni `APP_KEY` üretin.
- Telegram ayarları eski sistemde yalnızca `.env` üzerinden tanımlıysa `ENERJISA_TELEGRAM_BOT_TOKEN`, `ENERJISA_TELEGRAM_BOT_USERNAME` ve `ENERJISA_TELEGRAM_WEBHOOK_SECRET` değerlerini yeni `.env` dosyasına alın. Veritabanı taşıması ortam değişkenlerini kapsamaz.
- Merkezi SMTP'nin mevcut `MAIL_*` değerlerini alın. Yeni oturum çerezi ve varsayılan host kapsamı eski uygulamayla çakışmaz.
- `LEGACY_DB_*` için eski DB'ye SELECT yetkisi olan geçici bağlantı, `LEGACY_APP_KEY` için eski uygulamanın anahtarını girin. Bunları Git'e veya komut çıktısına koymayın.
- `REAKTIFRADAR_NOTIFICATIONS_ENABLED=false` kalsın. Scheduler profilini henüz açmayın.
- README'deki build / composer install / migrate / filament:assets adımlarını tamamlayın. Aynı APP_KEY tüm ReaktifRadar konteynerlerinde kullanılmalı.
- DNS A/AAAA kayıtlarını doğru sunucuya yönlendirin. Her iki alan adı için sertifika hazırlayın (`reaktifradar.com`, `www.reaktifradar.com`). Sertifika oluşmadan TLS bloğunu ortak nginx'e yüklemeyin. Mevcut ACME düzenini kullanın.
- `deploy/nginx.conf` bloğu ortak nginx yapılandırmasına eklenecek. Ortak nginx'e proje dizinini `/var/www/html/reaktifradar:ro` olarak mount edin. Nginx ve uygulama aynı shared ağda olmalı. `nginx -t` başarılı olmadan reload yapmayın.

## 2. Yazmayı ve eski gönderimi durdurma

- Eski uygulamada Enerjisa işlemlerini bakım penceresine alın: `/enerjisa` ve `/enerjisa/*` isteklerini ortak nginx'te geçici 503 ile kapatın. Enerjisa yönetim sayfalarında da değişiklik yapılmamalı. OkuKid'in diğer bölümleri çalışabilir.
- Eski backend `.env`: `ENERJISA_NOTIFICATIONS_ENABLED=false`. Bu anahtarı destekleyen bu görevdeki OkuKid değişikliklerini de yayınlayın. Eski config cache'i yenileyin ve scheduler'ı yeniden başlatın. Devam eden `enerjisa:notify` işlemlerinin bittiğini doğrulayın; yarıda kalan `sending` kayıtları otomatik tekrar gönderilmez.
- Yeni uygulama henüz ziyaretçi kabul etmesin; migration sırasında iki tarafta da Enerjisa yazma/gönderim olmamalı.
- Eski veritabanının yedeğini alın (servisinizin standart `pg_dump -Fc` yöntemi). Yedeği doğrulayın ve erişimini kısıtlayın.

## 3. Kopyalama ve kontrol

Yeni proje dizininde:

```sh
docker compose run --rm app php artisan reaktifradar:import
docker compose run --rm app php artisan reaktifradar:import --apply
```

İlk komut dry-run; ikincisi boş hedefe transaction içinde yazar. Her tablonun kayıt sayısı ve şifresi çözülmüş içeriğinin hash'i karşılaştırılır. Kaynak/ hedef aynıysa veya hedef doluysa işlem reddedilir. Hata sonrası tekrar denenebilir; kısmi müşteri kaydı kalmaz. Aktarım bittikten sonra dolu hedefe ikinci kez çalıştırmayın.

Eski yönetici ve müşteri parolaları geçerlidir; kullanıcıların yeniden giriş yapması gerekir. Firma, kullanıcı, tesisat sorgu geçmişi, reaktif analiz, alıcı adresi ve gönderim geçmişini kontrol edin. Aktarılan en büyük ID sonrasında yeni kayıt oluştuğunu test edin. API IP izni sunucu değişmiyorsa aynı çıkış IP'si ile devam eder; farklıysa tekrar kontrol edin.

```sh
docker compose up -d app
docker compose exec app php artisan optimize
```

HTTPS üzerinden `/enerjisa/login` ve `/panel/login` çalışmalı; bağlantılarda `reaktifradar.com` görünmeli.

## 4. Telegram ve bildirimlerin açılması

- Eski scheduler kapalı kalmalı.
- Aktarılan Telegram token/verileri korunur. Botun tek webhook'u olduğu için yeni HTTPS alan adı çalıştıktan sonra yeni uygulamada kaydedin:

```sh
docker compose exec app php artisan enerjisa:telegram-webhook
```

- `.env`: `REAKTIFRADAR_NOTIFICATIONS_ENABLED=true`; ardından:

```sh
docker compose exec app php artisan config:cache
docker compose --profile notifications up -d scheduler
```

- SMTP ve Telegram teslimatlarını, yönetim ekranındaki scheduler zamanını ve hata kayıtlarını kontrol edin. Başarılı gönderim kayıtları taşındığı için aynı gün/kanal tekrar gönderilmez.
- Eski `/enerjisa` yollarını yeni domaine aynı yolu koruyarak yönlendirin. Eski Enerjisa admin sayfalarını kullanımdan çıkarın. Eski tablolara dokunmayın; bir süre geri dönüş için saklayın.
- `LEGACY_DB_*`, `LEGACY_APP_KEY` ve geçmiş anahtarları yeni `.env` dosyasından kaldırıp config cache'i yenileyin. Geçici kaynak DB rolünün yetkisini kaldırın. Yeni database'in bağımsız yedeğini alın.

## Geri dönüş

Yeni uygulama henüz yazma/gönderim yapmadıysa yeni scheduler'ı durdurun, webhook'u eski uygulamada yeniden kaydedin, eski bakım engelini kaldırıp eski bildirim anahtarını açın. Yeni sistem veri aldıysa veya bildirim gönderdiyse eski uygulamayı doğrudan açmayın: yeni kayıtlar ve gönderim durumları önce uzlaştırılmalıdır. İki scheduler aynı anda çalışmamalı. Kaynak tablolar taşıma komutuyla silinmez.
