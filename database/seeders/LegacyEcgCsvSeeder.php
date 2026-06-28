<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class LegacyEcgCsvSeeder extends Seeder
{
    /**
     * Seed old ECG CSV samples into the local AF/Non-AF schema.
     */
    public function run(): void
    {
        $this->importCsv([
            database_path('seeders/data/anarianti_1_rs.csv'),
            database_path('seeders/data/3w.csv'),
        ], true, 360, 'EKG-LEGACY-001');

        $this->importCsv([
            database_path('seeders/data/ecg_lead_ii_filtered_bismil.csv'),
            database_path('seeders/data/ecg_lead_ii_filtered_ana_rianti.csv'),
            database_path('seeders/data/ecg_lead_ii_filtered_budi.csv'),
            database_path('seeders/data/ecg_lead_ii_filtered_ismail.csv'),
            database_path('seeders/data/ecg_lead_ii_filtered_junaidi.csv'),
            database_path('seeders/data/ecg_lead_ii_filtered_maisir.csv'),
            database_path('seeders/data/ecg_lead_ii_filtered_marjohan.csv'),
            database_path('seeders/data/ecg_lead_ii_filtered_susanto.csv'),
            database_path('seeders/data/ecg_lead_ii_filtered_tusyatin.csv'),
        ], false, 1000, 'EKG-EXCEL-001');
    }

    /**
     * @param  list<string>  $files
     */
    private function importCsv(array $files, bool $replace, int $sampleRate, string $deviceUid): void
    {
        Artisan::call('ekg:import-legacy-csv', [
            'files' => [
                ...$files,
            ],
            '--replace' => $replace,
            '--sample-rate' => $sampleRate,
            '--puskesmas-code' => 'PKM-001',
            '--device-uid' => $deviceUid,
        ]);

        $this->command?->line(Artisan::output());
    }
}
