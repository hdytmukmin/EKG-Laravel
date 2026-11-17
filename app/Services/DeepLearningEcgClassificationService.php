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
        $minimumSamples = max((int) config('ekg.minimum_samples', 5000), 2);
        if (count($rawSignal) < $minimumSamples) {
            return $this->pending(
                null,
                sprintf(
                    'Sinyal EKG belum mencukupi untuk prediksi: %s dari minimal %s sampel.',
                    number_format(count($rawSignal), 0, ',', '.'),
                    number_format($minimumSamples, 0, ',', '.')
                )
            );
        }

        if (! (bool) config('ekg.deep_learning.enabled', true)) {
            return $this->pending(null, 'Model deep learning belum diaktifkan.');
        }

        $pipelineDir = $this->resolvePath((string) config('ekg.deep_learning.model_dir', ''));
        $scriptPath = $this->resolvePath((string) config('ekg.deep_learning.predict_script', base_path('scripts/predict_dl_ecg.py')));
        $python = (string) config('ekg.deep_learning.python_binary', 'python');
        $weightsFile = (string) config('ekg.deep_learning.weights_file', 'best_dl_modelExp3LSTMTuned.pth');

        if ($pipelineDir === '' || ! is_dir($pipelineDir)) {
            return $this->pending(null, 'Folder pipeline model deep learning belum tersedia.');
        }

        if (! is_file($pipelineDir.DIRECTORY_SEPARATOR.$weightsFile)) {
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
            $result = Process::timeout(max((int) config('ekg.deep_learning.timeout', 180), 1))->run([
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
            logger()->warning('Deep learning ECG prediction failed', [
                'error' => $decoded['error'] ?? null,
                'traceback' => $decoded['traceback'] ?? null,
                'stderr' => $result->errorOutput(),
                'exit_code' => $result->exitCode(),
            ]);

            return $this->pending(
                basename($pipelineDir),
                (string) ($decoded['error'] ?? $result->errorOutput() ?: 'Prediksi deep learning gagal dijalankan.')
            );
        }

        return [
            'label' => $this->normalizeLabel((string) ($decoded['label'] ?? 'PENDING_MODEL')),
            'confidence' => isset($decoded['confidence']) ? (float) $decoded['confidence'] : null,
            'model_version' => $weightsFile,
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

        if (in_array($clean, ['NON_AF', 'NONAF', 'NORMAL'], true)) {
            return 'NON_AF';
        }

        if ($clean === 'AF' || str_contains($clean, 'ATRIAL_FIBRILLATION') || str_ends_with($clean, '_AF')) {
            return 'AF';
        }

        return $label;
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
