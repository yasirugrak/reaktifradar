<?php

declare(strict_types=1);

use App\Access\PanelRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            /**
             * VARSAYILAN EN AZ YETKI.
             *
             * Yeni bir personel hesabi kazayla her seye erisemez; rolu
             * bilincli olarak yukseltilmeli.
             */
            $table->string('role', 32)->default(PanelRole::Support->value)->after('email');
        });

        /**
         * VAR OLAN HESAPLAR KILITLENMEZ.
         *
         * Bu tablo migration'dan once yalnizca kurucu yonetici hesaplarini
         * iceriyordu. Onlari varsayilan (en dusuk) role dusurmek, calisan bir
         * kurulumda kimseyi panele sokamamak demekti - kullanicilari kilitlemek
         * asla bir varsayilanin yan etkisi olmamali.
         */
        DB::table('users')->update(['role' => PanelRole::SuperAdmin->value]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
