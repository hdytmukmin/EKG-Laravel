<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\EkgFeature;
use App\Models\Patient;
use App\Models\Prediction;
use App\Models\Puskesmas;
use App\Models\RecordEkg;
use App\Models\RecordingSession;
use App\Services\AfClassificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportLegacyRecordEkgCommand extends Command
{
    protected $signature = 'ekg:import-legacy-recordekg {--limit=0 : Batasi jumlah record yang diimport}';

    protected $description = 'Import optional legacy recordekg rows into the new AF/Non-AF schema for local testing.';

    public function handle(AfClassificationService $classifier): int
    {
        if (! Schema::hasTable('recordekg')) {
            $this->warn('Tabel legacy recordekg tidak ditemukan di database aktif. Import dilewati.');

            return self::SUCCESS;
        }

        $puskesmas = Puskesmas::query()->firstOrCreate(
            ['code' => 'PKM-001'],
            ['name' => 'Puskesmas 1']
        );

        $device = Device::query()->firstOrCreate(
            ['device_uid' => 'EKG-001'],
            [
                'puskesmas_id' => $puskesmas->id,
                'name' => 'Alat EKG 1',
                'status' => 'unknown',
            ]
        );

        $query = RecordEkg::query()->orderBy('id');
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $count = 0;
        foreach ($query->cursor() as $legacy) {
            $patient = Patient::query()->firstOrCreate(
                [
                    'puskesmas_id' => $puskesmas->id,
                    'name' => $legacy->nama ?: 'Pasien Legacy '.$legacy->id,
                ],
                [
                    'age' => is_numeric($legacy->umur) ? (int) $legacy->umur : null,
                    'gender' => $legacy->jk ?: null,
                    'address' => $legacy->alamat ?: null,
                    'external_subject_id' => $legacy->nama ?: null,
                ]
            );

            $session = RecordingSession::create([
                'puskesmas_id' => $puskesmas->id,
                'device_id' => $device->id,
                'patient_id' => $patient->id,
                'recorded_at' => $legacy->tglrekam ?: now(),
                'status' => 'completed',
                'source' => 'legacy_import',
            ]);

            $feature = EkgFeature::create([
                'recording_session_id' => $session->id,
                'subject' => $legacy->nama,
                'interval_pt' => is_numeric($legacy->tspt) ? (float) $legacy->tspt : null,
                'bpm' => is_numeric($legacy->bpm) ? (float) $legacy->bpm : null,
                'rr' => is_numeric($legacy->irr) ? (float) $legacy->irr : null,
                'rr_lokal' => is_numeric($legacy->irrlokal) ? (float) $legacy->irrlokal : null,
                'status' => 'legacy',
                'heart_rate' => $legacy->heartRateSeries(),
            ]);

            $prediction = $classifier->classify($feature);
            Prediction::create([
                'recording_session_id' => $session->id,
                'label' => $prediction['label'],
                'confidence' => $prediction['confidence'],
                'model_version' => $prediction['model_version'],
                'error_message' => $prediction['error_message'],
                'predicted_at' => now(),
            ]);

            $count++;
        }

        $this->info("Import legacy selesai: {$count} sesi dibuat.");

        return self::SUCCESS;
    }
}
