<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeepLearningEcgClassificationService
{
    /**
     * @param  list<float>  $rawSignal
     * @return array{label: string, confidence: float|null, model_version: string|null, error_message: string|null}
     */
    public function classify(array $rawSignal, int $sampleRate): array
    {
        if (! (bool) env('DL_MODEL_ENABLED', true)) {
            return $this->pending(null, 'Model deep learning belum diaktifkan.');
        }

        if (count($rawSignal) < max(2, $sampleRate * 5)) {
            return $this->pending(null, 'Sinyal EKG terlalu pendek untuk model deep learning. Minimal 5 detik.');
        }

        $pipelineDir = $this->resolvePath((string) env('DL_MODEL_DIR', ''));
        $scriptPath = $this->resolvePath((string) env('DL_PREDICT_SCRIPT', base_path('scripts/predict_dl_ecg.py')));
        $python = (string) env('PYTHON_BINARY', env('EKG_PYTHON_BIN', 'python'));

        if ($pipelineDir === '' || ! is_dir($pipelineDir)) {
            return $this->pending(null, 'Folder pipeline model deep learning belum tersedia.');
        }

        if (! is_file($pipelineDir.DIRECTORY_SEPARATOR.'best_dl_modelExp3LSTMTuned.pth')) {
            return $this->pending(basename($pipelineDir), 'File bobot model deep learning belum tersedia.');
        }

        if (! is_file($scriptPath)) {
            return $this->pending(basename($pipelineDir), 'Script adapter model deep learning belum tersedia.');
        }

        $payloadPath = 'tmp/dl-ecg-'.uniqid('', true).'.json';
        Storage::put($payloadPath, json_encode([
            'raw_signal' => $rawSignal,
            'sample_rate' => $sampleRate,
        ], JSON_THROW_ON_ERROR));

        try {
            $result = Process::timeout((int) env('DL_PREDICTION_TIMEOUT', env('EKG_PREDICTION_TIMEOUT', 180)))->run([
                $python,
                $scriptPath,
                '--pipeline-dir',
                $pipelineDir,
                '--input-json',
                Storage::path($payloadPath),
            ]);
        } catch (Throwable $exception) {
            Storage::delete($payloadPath);

            return $this->pending(basename($pipelineDir), $exception->getMessage());
        }

        Storage::delete($payloadPath);

        $decoded = json_decode($result->output(), true);
        if (! $result->successful() || ! is_array($decoded) || isset($decoded['error'])) {
            return $this->pending(
                basename($pipelineDir),
                (string) ($decoded['error'] ?? $result->errorOutput() ?: 'Prediksi deep learning gagal dijalankan.')
            );
        }

        return [
            'label' => $this->normalizeLabel((string) ($decoded['label'] ?? 'PENDING_MODEL')),
            'confidence' => isset($decoded['confidence']) ? (float) $decoded['confidence'] : null,
            'model_version' => 'best_dl_modelExp3LSTMTuned.pth',
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

        return match ($clean) {
            'NON_AF', 'NONAF', 'NORMAL' => 'NON_AF',
            'PERSISTENT_AF' => 'PERSISTENT_AF',
            'PAROXYSMAL_AF' => 'PAROXYSMAL_AF',
            'AF', 'ATRIAL_FIBRILLATION' => 'AF',
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
