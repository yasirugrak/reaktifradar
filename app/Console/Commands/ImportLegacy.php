<?php

namespace App\Console\Commands;

use App\Services\LegacyImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportLegacy extends Command
{
    protected $signature = 'reaktifradar:import {--apply : Verileri boş hedefe yazar; varsayılan yalnızca kontrol eder}';

    protected $description = 'OkuKid Enerjisa verilerini doğrulayarak ayrı veritabanına kopyalar';

    public function handle(LegacyImporter $importer): int
    {
        try {
            $counts = $importer->run(! $this->option('apply'));
        } catch (Throwable $exception) {
            // Never print SQL bindings, encrypted payloads, passwords or source keys.
            $this->error('Taşıma tamamlanamadı; hedef kayıtları geri alındı. Bağlantı, anahtar, şema ve boş hedef koşullarını kontrol edin.');
            $this->line('Hata türü: '.class_basename($exception));

            return self::FAILURE;
        }
        $this->table(['Tablo', 'Kayıt'], collect($counts)->map(fn ($count, $table) => [$table, $count])->values()->all());
        $this->info($this->option('apply') ? 'Kopyalama ve içerik doğrulaması tamamlandı. Kaynak veriler korunuyor.' : 'Kontrol tamamlandı. Veri yazılmadı; taşıma için --apply kullanın.');

        return self::SUCCESS;
    }
}
