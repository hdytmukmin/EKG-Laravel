<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\EkgFeature;
use App\Models\EkgRawSignal;
use App\Models\Patient;
use App\Models\Prediction;
use App\Models\Puskesmas;
use App\Models\RecordingSession;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $sites = collect([
            ['name' => 'Puskesmas 1', 'code' => 'PKM-001', 'address' => 'Alamat Puskesmas 1'],
            ['name' => 'Puskesmas 2', 'code' => 'PKM-002', 'address' => 'Alamat Puskesmas 2'],
            ['name' => 'Puskesmas 3', 'code' => 'PKM-003', 'address' => 'Alamat Puskesmas 3'],
        ])->map(fn (array $site) => Puskesmas::query()->updateOrCreate(['code' => $site['code']], $site));

        User::query()->updateOrCreate(['email' => 'superadmin@ekg.local'], [
            'name' => 'Super Admin',
            'role' => 'super_admin',
            'puskesmas_id' => null,
            'password' => Hash::make('password'),
        ]);

        $sites->each(function (Puskesmas $site, int $index) {
            $device = Device::query()->updateOrCreate(
                ['device_uid' => 'EKG-DEVICE-'.($index + 1)],
                [
                    'puskesmas_id' => $site->id,
                    'name' => 'Alat EKG '.($index + 1),
                    'mqtt_client_id' => 'ekg-device-'.($index + 1),
                    'status' => 'active',
                    'last_seen_at' => now()->subMinutes($index * 7),
                    'topic_map' => [
                        'subject' => 'building/subjek',
                        'interval_pt' => 'building/tspt',
                        'bpm' => 'building/bpm',
                        'rr' => 'building/RR',
                        'rr_lokal' => 'building/rrlokal',
                        'heart_rate' => 'building/hrr',
                        'raw' => 'building/rawdata',
                    ],
                ]
            );

            User::query()->updateOrCreate(['email' => 'admin'.($index + 1).'@ekg.local'], [
                'name' => 'Admin '.$site->name,
                'role' => 'admin_puskesmas',
                'puskesmas_id' => $site->id,
                'password' => Hash::make('password'),
            ]);

            $patient = Patient::query()->updateOrCreate(
                ['puskesmas_id' => $site->id, 'name' => 'Pasien Demo '.($index + 1)],
                [
                    'age' => 30 + $index,
                    'gender' => $index % 2 === 0 ? 'Laki-laki' : 'Perempuan',
                    'address' => 'Alamat pasien demo',
                    'external_subject_id' => 'DEMO-'.($index + 1),
                ]
            );

            for ($sessionIndex = 0; $sessionIndex < 2; $sessionIndex++) {
                $session = RecordingSession::query()->create([
                    'puskesmas_id' => $site->id,
                    'device_id' => $device->id,
                    'patient_id' => $patient->id,
                    'recorded_at' => now()->subDays($sessionIndex)->subHours($index),
                    'status' => 'completed',
                    'source' => 'seed',
                ]);

                $bpm = 72 + ($index * 9) + ($sessionIndex * 5);
                $heartRate = collect(range(1, 180))->map(fn ($point) => round($bpm + sin($point / 8) * 7 + rand(-20, 20) / 10, 2))->all();
                $raw = collect(range(1, 1200))->map(fn ($point) => round(sin($point / 18) * 0.7 + sin($point / 5) * 0.08, 5))->all();
                $label = ($index === 1 || ($index === 2 && $sessionIndex === 1)) ? 'AF' : 'NON_AF';

                EkgFeature::query()->create([
                    'recording_session_id' => $session->id,
                    'subject' => $patient->name,
                    'interval_pt' => 0.48 + ($index * 0.02),
                    'bpm' => $bpm,
                    'rr' => 0.9 + ($index * 0.03),
                    'rr_lokal' => 120 + ($index * 18),
                    'status' => $label,
                    'sdnn' => null,
                    'sns' => null,
                    'heart_rate' => $heartRate,
                ]);

                EkgRawSignal::query()->create([
                    'recording_session_id' => $session->id,
                    'voltage_values' => $raw,
                    'sample_rate' => 230,
                    'total_samples' => count($raw),
                ]);

                Prediction::query()->create([
                    'recording_session_id' => $session->id,
                    'label' => $label,
                    'confidence' => 0.8 + ($sessionIndex / 20),
                    'model_version' => 'seed-placeholder',
                    'predicted_at' => now(),
                ]);
            }
        });
    }
}
