<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\EkgFeature;
use App\Models\EkgRawSignal;
use App\Models\Patient;
use App\Models\Prediction;
use App\Models\Puskesmas;
use App\Models\RecordingSession;
use App\Services\AfClassificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyEcgCsvCommand extends Command
{
    protected $signature = 'ekg:import-legacy-csv
        {files* : CSV file paths containing one voltage value per line}
        {--replace : Replace local ECG patient/session/signal data before import}
        {--sample-rate=360 : Sampling frequency in Hz}
        {--puskesmas-code=PKM-001 : Target puskesmas code}
        {--device-uid=EKG-LEGACY-001 : Target device uid}';

    protected $description = 'Import old ECG CSV files into the Laravel AF/Non-AF schema.';

    public function handle(): int
    {
        $sampleRate = max(1, (int) $this->option('sample-rate'));
        $classifier = app(AfClassificationService::class);

        if ($this->option('replace')) {
            $this->replaceLocalEcgData();
        }

        $puskesmas = Puskesmas::query()->firstOrCreate(
            ['code' => (string) $this->option('puskesmas-code')],
            ['name' => 'Puskesmas 1']
        );

        $device = Device::query()->updateOrCreate(
            ['device_uid' => (string) $this->option('device-uid')],
            [
                'puskesmas_id' => $puskesmas->id,
                'name' => 'Alat EKG Legacy',
                'status' => 'online',
                'last_seen_at' => now(),
            ]
        );

        $imported = 0;
        foreach ($this->argument('files') as $path) {
            if (! is_file($path)) {
                $this->warn("File tidak ditemukan: {$path}");
                continue;
            }

            $values = $this->readVoltageValues($path);
            if (count($values) < $sampleRate) {
                $this->warn("File dilewati karena sample terlalu sedikit: {$path}");
                continue;
            }

            $metadata = $this->patientMetadataFromPath($path);
            $subject = $metadata['subject'];
            $filtered = $this->baselineFiltered($values, (int) round($sampleRate * 0.22), 0.42);
            $rPeaks = $this->detectRPeaks($filtered, $sampleRate);
            $rrIntervals = $this->rrIntervals($rPeaks, $sampleRate);
            $bpm = $this->bpmFromRr($rrIntervals);
            $heartRate = $this->heartRateSeries($rrIntervals);
            $sdnn = $this->sdnn($rrIntervals);
            $rmssd = $this->rmssd($rrIntervals);

            $patient = Patient::query()->updateOrCreate(
                [
                    'puskesmas_id' => $puskesmas->id,
                    'external_subject_id' => $subject,
                ],
                [
                    'name' => $metadata['name'],
                    'age' => $metadata['age'],
                    'gender' => $metadata['gender'],
                    'address' => $metadata['address'],
                ]
            );

            $session = RecordingSession::create([
                'puskesmas_id' => $puskesmas->id,
                'device_id' => $device->id,
                'patient_id' => $patient->id,
                'recorded_at' => now()->subMinutes(10 - $imported),
                'status' => 'completed',
                'source' => 'legacy_csv',
                'notes' => basename($path),
            ]);

            $feature = EkgFeature::create([
                'recording_session_id' => $session->id,
                'subject' => $subject,
                'interval_pt' => null,
                'bpm' => $bpm,
                'rr' => $rrIntervals ? array_sum($rrIntervals) / count($rrIntervals) : null,
                'rr_lokal' => $rrIntervals ? end($rrIntervals) : null,
                'status' => 'legacy_csv',
                'sdnn' => $sdnn,
                'sns' => $rmssd,
                'heart_rate' => $heartRate,
            ]);

            $prediction = $classifier->classify($feature);
            if ($prediction['label'] === 'PENDING_MODEL') {
                $prediction = $this->fallbackPrediction($sdnn, $rmssd, $prediction['error_message']);
            }

            EkgRawSignal::create([
                'recording_session_id' => $session->id,
                'voltage_values' => $this->roundSeries($values, 6),
                'filtered_values' => $this->roundSeries($filtered, 6),
                'r_peak_indices' => $rPeaks,
                'sample_rate' => $sampleRate,
                'total_samples' => count($values),
            ]);

            Prediction::create([
                'recording_session_id' => $session->id,
                'label' => $prediction['label'],
                'confidence' => $prediction['confidence'],
                'model_version' => $prediction['model_version'],
                'error_message' => $prediction['error_message'],
                'predicted_at' => now(),
            ]);

            $this->info(sprintf(
                '%s imported: %d sample, %d R-peaks, BPM %.1f',
                basename($path),
                count($values),
                count($rPeaks),
                $bpm ?? 0
            ));

            $imported++;
        }

        $this->info("Import selesai: {$imported} file.");

        return self::SUCCESS;
    }

    private function replaceLocalEcgData(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Prediction::query()->delete();
        EkgRawSignal::query()->delete();
        EkgFeature::query()->delete();
        RecordingSession::query()->delete();
        Patient::query()->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->warn('Data pasien dan sesi EKG lokal sudah diganti.');
    }

    /**
     * @return list<float>
     */
    private function readVoltageValues(string $path): array
    {
        $values = [];
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return $values;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '' || ! is_numeric($line)) {
                continue;
            }

            $values[] = (float) $line;
        }

        fclose($handle);

        return $values;
    }

    /**
     * @return array{subject: string, name: string, age: int, gender: string, address: string}
     */
    private function patientMetadataFromPath(string $path): array
    {
        $filename = strtolower(pathinfo($path, PATHINFO_FILENAME));
        $subject = str($filename)
            ->replace('_rs', '')
            ->replace('_', ' ')
            ->title()
            ->toString();

        $knownPatients = [
            'anarianti_1_rs' => [
                'subject' => 'ANARIANTI-001',
                'name' => 'Anarianti',
                'age' => 43,
                'gender' => 'Perempuan',
                'address' => 'Jl. Melati No. 12, Pekanbaru',
            ],
            '3w' => [
                'subject' => 'WAHYU-003',
                'name' => 'Wahyu Saputra',
                'age' => 51,
                'gender' => 'Laki-laki',
                'address' => 'Jl. Cendana No. 8, Pekanbaru',
            ],
            'ecg_lead_ii_filtered_bismil' => [
                'subject' => 'BISMIL-001',
                'name' => 'Bismil',
                'age' => 46,
                'gender' => 'Laki-laki',
                'address' => 'Jl. Rajawali No. 14, Pekanbaru',
            ],
            'ecg_lead_ii_filtered_ana_rianti' => [
                'subject' => 'ANA-RIANTI-002',
                'name' => 'Ana Rianti',
                'age' => 43,
                'gender' => 'Perempuan',
                'address' => 'Jl. Melati No. 12, Pekanbaru',
            ],
            'ecg_lead_ii_filtered_budi' => [
                'subject' => 'BUDI-003',
                'name' => 'Budi Santoso',
                'age' => 55,
                'gender' => 'Laki-laki',
                'address' => 'Jl. Kenanga No. 5, Pekanbaru',
            ],
            'ecg_lead_ii_filtered_ismail' => [
                'subject' => 'ISMAIL-004',
                'name' => 'Ismail',
                'age' => 49,
                'gender' => 'Laki-laki',
                'address' => 'Jl. Durian No. 21, Pekanbaru',
            ],
            'ecg_lead_ii_filtered_junaidi' => [
                'subject' => 'JUNAIDI-005',
                'name' => 'Junaidi',
                'age' => 57,
                'gender' => 'Laki-laki',
                'address' => 'Jl. Teratai No. 9, Pekanbaru',
            ],
            'ecg_lead_ii_filtered_maisir' => [
                'subject' => 'MAISIR-006',
                'name' => 'Maisir',
                'age' => 52,
                'gender' => 'Laki-laki',
                'address' => 'Jl. Nangka No. 18, Pekanbaru',
            ],
            'ecg_lead_ii_filtered_marjohan' => [
                'subject' => 'MARJOHAN-007',
                'name' => 'Marjohan',
                'age' => 60,
                'gender' => 'Laki-laki',
                'address' => 'Jl. Seroja No. 7, Pekanbaru',
            ],
            'ecg_lead_ii_filtered_susanto' => [
                'subject' => 'SUSANTO-008',
                'name' => 'Susanto',
                'age' => 54,
                'gender' => 'Laki-laki',
                'address' => 'Jl. Anggrek No. 10, Pekanbaru',
            ],
            'ecg_lead_ii_filtered_tusyatin' => [
                'subject' => 'TUSYATIN-009',
                'name' => 'Tusyatin',
                'age' => 58,
                'gender' => 'Perempuan',
                'address' => 'Jl. Flamboyan No. 16, Pekanbaru',
            ],
        ];

        return $knownPatients[$filename] ?? [
            'subject' => str($subject)->upper()->replace(' ', '-')->toString(),
            'name' => $subject,
            'age' => 45,
            'gender' => 'Laki-laki',
            'address' => 'Pekanbaru',
        ];
    }

    /**
     * @param list<float> $values
     * @return list<float>
     */
    private function baselineFiltered(array $values, int $window, float $scale): array
    {
        $window = max(5, $window);
        $half = intdiv($window, 2);
        $prefix = [0.0];
        foreach ($values as $value) {
            $prefix[] = end($prefix) + $value;
        }

        $filtered = [];
        $count = count($values);
        for ($i = 0; $i < $count; $i++) {
            $start = max(0, $i - $half);
            $end = min($count - 1, $i + $half);
            $mean = ($prefix[$end + 1] - $prefix[$start]) / max(1, $end - $start + 1);
            $filtered[] = ($values[$i] - $mean) * $scale;
        }

        return $filtered;
    }

    /**
     * @param list<float> $filtered
     * @return list<int>
     */
    private function detectRPeaks(array $filtered, int $sampleRate): array
    {
        $maxAbs = max(array_map(fn (float $value): float => abs($value), $filtered));
        $threshold = max($maxAbs * 0.18, 0.03);
        $minDistance = (int) round($sampleRate * 0.32);
        $peaks = [];
        $lastPeak = -$minDistance;

        $count = count($filtered);
        for ($i = 2; $i < $count - 2; $i++) {
            if (abs($filtered[$i]) < $threshold || $i - $lastPeak < $minDistance) {
                continue;
            }

            $isPositivePeak = $filtered[$i] >= $filtered[$i - 1] && $filtered[$i] >= $filtered[$i + 1];
            $isNegativePeak = $filtered[$i] <= $filtered[$i - 1] && $filtered[$i] <= $filtered[$i + 1];

            if ($isPositivePeak || $isNegativePeak) {
                $peaks[] = $i;
                $lastPeak = $i;
            }
        }

        return $peaks;
    }

    /**
     * @param list<int> $rPeaks
     * @return list<float>
     */
    private function rrIntervals(array $rPeaks, int $sampleRate): array
    {
        $rr = [];
        for ($i = 1; $i < count($rPeaks); $i++) {
            $rr[] = ($rPeaks[$i] - $rPeaks[$i - 1]) / $sampleRate;
        }

        return $rr;
    }

    /**
     * @param list<float> $rrIntervals
     */
    private function bpmFromRr(array $rrIntervals): ?float
    {
        if (! $rrIntervals) {
            return null;
        }

        $mean = array_sum($rrIntervals) / count($rrIntervals);

        return $mean > 0 ? 60 / $mean : null;
    }

    /**
     * @param list<float> $rrIntervals
     * @return list<float>
     */
    private function heartRateSeries(array $rrIntervals): array
    {
        return array_map(fn (float $rr): float => round($rr > 0 ? 60 / $rr : 0, 2), $rrIntervals);
    }

    /**
     * @param list<float> $rrIntervals
     */
    private function sdnn(array $rrIntervals): ?float
    {
        if (count($rrIntervals) < 2) {
            return null;
        }

        $mean = array_sum($rrIntervals) / count($rrIntervals);
        $variance = array_sum(array_map(fn (float $rr): float => ($rr - $mean) ** 2, $rrIntervals)) / (count($rrIntervals) - 1);

        return sqrt($variance) * 1000;
    }

    /**
     * @param list<float> $rrIntervals
     */
    private function rmssd(array $rrIntervals): ?float
    {
        if (count($rrIntervals) < 2) {
            return null;
        }

        $squares = [];
        for ($i = 1; $i < count($rrIntervals); $i++) {
            $diffMs = ($rrIntervals[$i] - $rrIntervals[$i - 1]) * 1000;
            $squares[] = $diffMs ** 2;
        }

        return sqrt(array_sum($squares) / count($squares));
    }

    /**
     * @return array{label: string, confidence: float|null, error_message: string|null}
     */
    private function fallbackPrediction(?float $sdnn, ?float $rmssd, ?string $modelError = null): array
    {
        if ($sdnn === null || $rmssd === null) {
            return [
                'label' => 'PENDING_MODEL',
                'confidence' => null,
                'model_version' => null,
                'error_message' => 'Fitur SDNN/RMSSD belum cukup untuk klasifikasi fallback.',
            ];
        }

        $irregularityScore = ($sdnn * 0.65) + ($rmssd * 0.35);
        $isAf = $irregularityScore >= 110;

        return [
            'label' => $isAf ? 'AF' : 'NON_AF',
            'confidence' => $isAf ? 0.82 : 0.78,
            'model_version' => 'legacy-csv-fallback',
            'error_message' => trim('Klasifikasi fallback berbasis SDNN/RMSSD.'.($modelError ? ' Model: '.$modelError : '')),
        ];
    }

    /**
     * @param list<float> $values
     * @return list<float>
     */
    private function roundSeries(array $values, int $precision): array
    {
        return array_map(fn (float $value): float => round($value, $precision), $values);
    }
}
