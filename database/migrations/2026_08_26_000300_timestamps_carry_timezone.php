<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Saat dilimsiz zaman damgalarini SAAT DILIMLI hale getirir.
     *
     * NEDEN GEREKLI: uygulama saati Istanbul'a alinirken bu adim
     * atlanirsa MEVCUT KAYITLARIN ANLAMI DEGISIR. 'timestamp without
     * time zone' kolonlari mutlak bir an degil, ciplak bir sayi tutuyor;
     * o sayinin hangi saat diliminde okunacagini uygulama belirliyor.
     * Bugune kadar UTC yazildilar. Uygulama Istanbul'a gectiginde ayni
     * sayilar Istanbul saati diye okunur ve gecmisteki her kayit uc saat
     * geriye kayar - hicbir hata uretmeden.
     *
     * Donusum kaynagi ACIKCA UTC diye bildiriliyor; PostgreSQL degeri
     * mutlak ana cevirip saklıyor. Bundan sonra uygulamanin saat dilimi
     * yalnizca GOSTERIMI etkiliyor, saklanani degil.
     *
     * Yeni tablolar zaten timestampTz kullaniyordu (bildirimler, uretim
     * gunlugu); bu goc, eski tablolari onlarla ayni hizaya getiriyor.
     */
    public function up(): void
    {
        foreach ($this->naiveColumns() as [$table, $column]) {
            // Tablo ve kolon adlari information_schema'dan geliyor,
            // disaridan degil; yine de tirnak icinde birakiliyor.
            DB::statement(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE timestamptz USING %s AT TIME ZONE \'UTC\'',
                '"'.$table.'"',
                '"'.$column.'"',
                '"'.$column.'"',
            ));
        }
    }

    public function down(): void
    {
        // GERI DONUS UTC'YE. Kolon tekrar ciplak hale gelirken degerin
        // hangi dilimde yazilacagini soylemek zorundayiz; UTC yaziyoruz
        // cunku eski hal buydu.
        foreach ($this->tzColumns() as [$table, $column]) {
            DB::statement(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE timestamp USING %s AT TIME ZONE \'UTC\'',
                '"'.$table.'"',
                '"'.$column.'"',
                '"'.$column.'"',
            ));
        }
    }

    /**
     * Hala saat dilimsiz olan kolonlar.
     *
     * TEKRAR CALISTIRILABILIR: donusmus kolonlar listeye girmiyor, yani
     * goc iki kez kosarsa ikinci kosu hicbir sey yapmiyor.
     *
     * @return list<array{string, string}>
     */
    private function naiveColumns(): array
    {
        return $this->columnsOfType('timestamp without time zone');
    }

    /**
     * @return list<array{string, string}>
     */
    private function tzColumns(): array
    {
        return $this->columnsOfType('timestamp with time zone');
    }

    /**
     * @return list<array{string, string}>
     */
    private function columnsOfType(string $type): array
    {
        $rows = DB::select(
            "SELECT table_name, column_name
             FROM information_schema.columns
             WHERE table_schema = 'public' AND data_type = ?
             ORDER BY table_name, column_name",
            [$type],
        );

        $out = [];

        foreach ($rows as $row) {
            /** @var object{table_name: string, column_name: string} $row */
            $out[] = [$row->table_name, $row->column_name];
        }

        return $out;
    }
};
