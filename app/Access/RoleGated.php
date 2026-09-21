<?php

declare(strict_types=1);

namespace App\Access;

use Illuminate\Support\Facades\Auth;

/**
 * Panel kaynaklarina rol kapisi.
 *
 * Kaynak yalnizca hangi ROLLERIN erisebilecegini bildirir; kontrolun kendisi
 * tek yerde durur. Her kaynakta ayri ayri yazilan yetki kontrolu, zamanla
 * birinde eksik kalir - ve eksik kalan yetki kontrolu hicbir hata uretmez,
 * sadece sessizce acik kalir.
 *
 * MENUDEN GIZLEMEK YETMEZ: canViewAny() Filament'in hem menusunu hem
 * rotasini kapatir. Yalnizca menuyu gizlemek, adresi bilen birinin sayfaya
 * girebilmesi anlamina gelirdi.
 */
trait RoleGated
{
    /**
     * Bu kaynaga erisebilen roller.
     *
     * @return list<PanelRole>
     */
    abstract public static function allowedRoles(): array;

    public static function currentRole(): PanelRole
    {
        $user = Auth::user();

        // Kimlik yoksa veya rol tasimiyorsa en dusuk yetki: kapi varsayilan
        // olarak KAPALI. Personel modeli (App\Models\User) burada TANINMAZ;
        // yalnizca Shared arayuzu bilinir - aksi halde modul sinirlari app/
        // uzerinden birbirine sizardi.
        return $user instanceof HasPanelRole ? $user->panelRole() : PanelRole::Support;
    }

    public static function canViewAny(): bool
    {
        return in_array(self::currentRole(), static::allowedRoles(), true);
    }

    public static function canView(mixed $record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return self::canViewAny() && self::currentRole()->managesContent();
    }

    public static function canEdit(mixed $record): bool
    {
        return self::canCreate();
    }

    public static function canDelete(mixed $record): bool
    {
        return self::currentRole() === PanelRole::SuperAdmin;
    }

    public static function canDeleteAny(): bool
    {
        return self::currentRole() === PanelRole::SuperAdmin;
    }
}
