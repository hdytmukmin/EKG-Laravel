<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Throwable;

class SdnnSnsExtractorService
{
    /**
     * Ekstraksi mengikuti notebook model:
     * bandpass -> notch -> detrend -> standardize -> ecg_peaks -> hrv_time.
     *
     * Catatan: kolom PRD "SNS" sementara dipetakan ke HRV_RMSSD karena
     * best_model.pkl dilatih dengan fitur HRV_RMSSD.
     *
     * @param  array<int, float|int|string>  $rawValues
     * @return array{sdnn: float|null, sns: float|null}
     */
    public function extract(array $rawValues): array
    {
        $numericValues = array_values(array_filter(
            array_map(fn ($value) => is_numeric($value) ? (float) $value : null, $rawValues),
            fn ($value) => $value !== null
        ));

        if (count($numericValues) < 30) {
            return $this->emptyResult();
        }

        $python = (string) env('PYTHON_BINARY', env('EKG_PYTHON_BIN', 'python'));
        $scriptPath = $this->resolvePath((string) env('EKG_HRV_SCRIPT', base_path('scripts/extract_hrv.py')));
        $sampleRate = (int) env('EKG_SAMPLE_RATE', 250);

        if (! is_file($scriptPath)) {
            return $this->emptyResult();
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'ekg_hrv_');
        if ($tempPath === false) {
            return $this->emptyResult();
        }

        try {
            file_put_contents($tempPath, json_encode(['raw_values' => $numericValues], JSON_THROW_ON_ERROR));

            $result = Process::timeout(30)->run([
                $python,
                $scriptPath,
                '--input-json',
                $tempPath,
                '--sample-rate',
                (string) $sampleRate,
            ]);

            $decoded = json_decode($result->output(), true);
            if (! $result->successful() || ! is_array($decoded) || isset($decoded['error'])) {
                return $this->emptyResult();
            }

            return [
                'sdnn' => isset($decoded['sdnn']) && is_numeric($decoded['sdnn']) ? (float) $decoded['sdnn'] : null,
                'sns' => isset($decoded['rmssd']) && is_numeric($decoded['rmssd']) ? (float) $decoded['rmssd'] : null,
            ];
        } catch (Throwable) {
            return $this->emptyResult();
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * @return array{sdnn: float|null, sns: float|null}
     */
    private function emptyResult(): array
    {
        return [
            'sdnn' => null,
            'sns' => null,
        ];
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }
}
