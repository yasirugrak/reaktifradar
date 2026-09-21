<?php

declare(strict_types=1);

namespace App\Models;

use App\Access\HasPanelRole;
use App\Access\PanelRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** ReaktifRadar sistem yöneticisi; müşteri hesapları ayrı tutulur. */
#[Fillable(['name', 'email', 'password', 'role', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasPanelRole
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => PanelRole::class,
        ];
    }

    /**
     * Panele kimin girebilecegi.
     *
     * Rol her hesapta dolu (varsayilan en dusuk yetki), bu yuzden kapi
     * yalnizca e-posta dogrulamasina bakar; yetki ayrimi kaynak duzeyinde
     * yapilir.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Hesabin panel rolu.
     *
     * Cast normalde PanelRole dondurur; yine de ham deger uzerinden
     * cozumleniyor cunku veritabanina elle girilmis veya artik var olmayan
     * bir rol degeri EN DUSUK yetkiye dusmeli. Bilinmeyen bir rolun sessizce
     * super yonetici gibi davranmasi, en pahali hata sinifi olurdu.
     */
    public function panelRole(): PanelRole
    {
        return PanelRole::tryFrom((string) $this->getRawOriginal('role')) ?? PanelRole::Support;
    }

    public function isSuperAdmin(): bool
    {
        return $this->panelRole() === PanelRole::SuperAdmin;
    }
}
