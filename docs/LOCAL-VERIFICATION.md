# Yerel doğrulama

- Aynı `okukid-postgres` servisinde ayrı `reaktifradar` database oluşturuldu.
- Yerel kaynakta 1 süper yönetici, 0 Enerjisa firma/kullanıcı/sorgu/bildirim kaydı vardı.
- Yönetici kaydı ID ve parola hash'i korunarak yeni database'e kopyalandı; içerik doğrulaması geçti.
- Kaynak veriler silinmedi. Geçici kaynak bağlantı bilgileri ve eski APP_KEY yeni yerel `.env` dosyasından kaldırıldı.
- 83 test / 527 assertion geçti. Kaynak OkuKid Enerjisa testleri: 80 / 508 assertion geçti.
- PHPStan ve Composer doğrulaması geçti. Müşteri ve yönetici giriş sayfaları HTTP 200 döndü.
- Docker imajı başarıyla oluşturuldu; imaj içinde üretim bağımlılıklarının PHP/eklenti kontrolleri geçti.
- Laravel config/route/view cache oluşturma kontrolleri geçti.
- Bildirimler kapalı; dışarıya test e-postası veya Telegram mesajı gönderilmedi.

Canlı veri aktarımı ve reaktifradar.com yayını yapılmadı. Canlı kaynak veriler yerel database'de bulunmuyor; sunucu erişimi ve geçiş planı uygulanmalı.
