<?php

declare(strict_types=1);

namespace App\Access;

/**
 * Panel rolu tasiyan kimlik.
 *
 * NEDEN ARAYUZ: rol kontrolu yapan panel kaynaklari App\Models\User'i
 * TANIMAMALI. Personel kullanicisi cerceve tarafina (app/) ait bir modeldir;
 * moduller ona bagimli olursa modul sinirlari app/ uzerinden birbirine
 * sizar - Deptrac bu ihlali ilk denemede yakaladi.
 *
 * Moduller bu arayuzu gorur, App\Models\User onu karsilar. Ileride personel
 * kimligi ayri bir modul veya dis kimlik saglayicisina tasinsa, panel
 * kaynaklarinda tek satir degismez.
 */
interface HasPanelRole
{
    public function panelRole(): PanelRole;
}
