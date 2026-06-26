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
        Artisan::call('ekg:import-legacy-csv', [
            'files' => [
                database_path('seeders/data/anarianti_1_rs.csv'),
                database_path('seeders/data/3w.csv'),
            ],
            '--replace' => true,
            '--sample-rate' => 360,
            '--puskesmas-code' => 'PKM-001',
            '--device-uid' => 'EKG-LEGACY-001',
        ]);

        $this->command?->line(Artisan::output());
    }
}
