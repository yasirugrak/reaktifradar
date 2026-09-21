<?php

declare(strict_types=1);

namespace App\Access;

/**
 * Yonetim paneli rolleri.
 *
 * Uc rol var cunku panelde uc farkli is yapiliyor ve bunlarin risk profilleri
 * ayni degil:
 *
 *   Icerik editoru  metin ve kelime yazar. En kalabalik rol olacak ve buyuk
 *                   ihtimalle disaridan calisan kisiler olacak. Uzaktan
 *                   yapilandirmaya erisimi OLMAMALI: yanlis bir esik degeri
 *                   butun kullanicilari uygulamadan kilitleyebilir.
 *
 *   Destek          veliden gelen soruyu cevaplar. Cocuk profilini GORMESI
 *                   gerekir ama DEGISTIRMESI gerekmez. Salt-okunur olmasi
 *                   bir kisitlama degil, destek personelini koruyan bir
 *                   tasarim: yanlislikla veri bozma ihtimali yok.
 *
 *   Super yonetici  her seye erisir. Bilincli olarak az kisi olmali.
 *
 * NEDEN PAKET DEGIL: uc rol ve bir avuc yetki icin tam bir yetkilendirme
 * paketi kurmak, anlasilmasi gereken seyi bir yapilandirma katmaninin
 * arkasina saklar. Buyudugunde tasinabilir; simdi acik olmasi daha degerli.
 */
enum PanelRole: string
{
    case SuperAdmin = 'super_admin';
    case ContentEditor = 'content_editor';
    case Support = 'support';
    case Influencer = 'influencer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super yonetici',
            self::ContentEditor => 'Icerik editoru',
            self::Support => 'Destek',
            self::Influencer => 'Influencer',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Panelin tamamina erisir.',
            self::ContentEditor => 'Okuma metinleri, kelime havuzu ve etkinlik tanimlari.',
            self::Support => 'Cocuk profillerini yalnizca goruntuler; hicbir seyi degistiremez.',
            self::Influencer => 'Yalnızca kendi tıklama, kayıt ve komisyon verilerini görür.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** Icerik uretebilir mi? */
    public function managesContent(): bool
    {
        return $this === self::SuperAdmin || $this === self::ContentEditor;
    }

    /**
     * Sistem ayarlarina (uzaktan yapilandirma, duyurular) erisebilir mi?
     *
     * Bu ayarlar tum kullanicilari ayni anda etkiler: yanlis bir minimum
     * surum degeri herkesi zorunlu guncelleme ekraninda birakir. Bu yuzden
     * yalnizca super yonetici.
     */
    public function managesSystem(): bool
    {
        return $this === self::SuperAdmin;
    }

    /** Cocuk profillerini goruntuleyebilir mi? */
    public function viewsChildren(): bool
    {
        return $this === self::SuperAdmin || $this === self::Support;
    }
}
