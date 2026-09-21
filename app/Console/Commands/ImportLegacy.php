<?php

namespace App\Console\Commands;

use App\Services\ImportFailure;
use App\Services\LegacyImporter;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
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
            $this->error($this->option('apply') ? 'Taşıma tamamlanamadı; hedef kayıtları geri alındı.' : 'Ön kontrol tamamlanamadı. Veri yazılmadı.');
            $this->line('Hata türü: '.class_basename($exception));
            if ($exception instanceof QueryException) {
                $diagnosis = ImportFailure::describe($exception);
                $this->line('Bağlantı: '.$diagnosis['connection']);
                $this->line('SQLSTATE: '.$diagnosis['state']);
                $this->error($diagnosis['reason']);
            }

            return self::FAILURE;
        }
        $this->table(['Tablo', 'Kayıt'], collect($counts)->map(fn ($count, $table) => [$table, $count])->values()->all());
        $this->info($this->option('apply') ? 'Kopyalama ve içerik doğrulaması tamamlandı. Kaynak veriler korunuyor.' : 'Kontrol tamamlandı. Veri yazılmadı; taşıma için --apply kullanın.');

        return self::SUCCESS;
    }
}
