<?php

namespace App\Services;

use App\Models\EkgFeature;
use Illuminate\Support\Facades\Process;
use Throwable;

class AfClassificationService
{
    /**
     * @return array{label: string, confidence: float|null, model_version: string|null, error_message: string|null}
     */
    public function classify(EkgFeature $feature): array
    {
        $modelPath = $this->resolvePath((string) config('services.af_model.path', env('AF_MODEL_PATH', '')));
        $scriptPath = $this->resolvePath((string) env('AF_PREDICT_SCRIPT', base_path('scripts/predict_af.py')));
        $python = (string) env('PYTHON_BINARY', env('EKG_PYTHON_BIN', 'python'));

        if ($modelPath === '' || ! is_file($modelPath)) {
            return [
                'label' => 'PENDING_MODEL',
                'confidence' => null,
                'model_version' => null,
                'error_message' => 'Model AF/Non-AF belum tersedia di environment ini.',
            ];
        }

        if (! is_file($scriptPath)) {
            return $this->pending(basename($modelPath), 'Script adapter model AF/Non-AF belum tersedia.');
        }

        $features = [
            'BPM' => $feature->bpm,
            'RR_mean(s)' => $feature->rr,
            'HRV_SDNN' => $feature->sdnn,
            'HRV_RMSSD' => $feature->sns,
        ];

        foreach ($features as $name => $value) {
            if (! is_numeric($value)) {
                return $this->pending(basename($modelPath), "Fitur {$name} belum tersedia.");
            }
        }

        try {
            $result = Process::timeout((int) env('EKG_PREDICTION_TIMEOUT', 60))->run([
                $python,
                $scriptPath,
                '--model',
                $modelPath,
                '--features-json',
                json_encode($features, JSON_THROW_ON_ERROR),
            ]);
        } catch (Throwable $exception) {
            return $this->pending(basename($modelPath), $exception->getMessage());
        }

        $decoded = json_decode($result->output(), true);
        if (! $result->successful() || ! is_array($decoded) || isset($decoded['error'])) {
            return $this->pending(
                basename($modelPath),
                (string) ($decoded['error'] ?? $result->errorOutput() ?: 'Prediksi model gagal dijalankan.')
            );
        }

        return [
            'label' => $this->normalizeLabel((string) ($decoded['label'] ?? 'PENDING_MODEL')),
            'confidence' => isset($decoded['confidence']) ? (float) $decoded['confidence'] : null,
            'model_version' => basename($modelPath),
            'error_message' => null,
        ];
    }

    /**
     * @return array{label: string, confidence: float|null, model_version: string|null, error_message: string|null}
     */
    private function pending(?string $modelVersion, string $message): array
    {
        return [
            'label' => 'PENDING_MODEL',
            'confidence' => null,
            'model_version' => $modelVersion,
            'error_message' => $message,
        ];
    }

    private function normalizeLabel(string $label): string
    {
        $clean = strtoupper(str_replace([' ', '-'], '_', trim($label)));

        if (str_contains($clean, 'NON_ATRIAL_FIBRILLATION') || str_contains($clean, 'NON_AF')) {
            return 'NON_AF';
        }

        if (str_contains($clean, 'ATRIAL_FIBRILLATION') || $clean === 'AF') {
            return 'AF';
        }

        return match ($clean) {
            'AF', 'ATRIAL_FIBRILLATION' => 'AF',
            'NON_AF', 'NONAF', 'NORMAL' => 'NON_AF',
            default => $label,
        };
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
