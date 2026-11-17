<?php

namespace App\Console\Commands;

use App\Services\DeepLearningEcgClassificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Throwable;

class CheckDeepLearningModelCommand extends Command
{
    protected $signature = 'ekg:model-check {--run : Jalankan inferensi uji dengan sinyal sintetis}';

    protected $description = 'Memeriksa konfigurasi, dependency Python, dan pipeline model deep learning EKG';

    public function handle(DeepLearningEcgClassificationService $classifier): int
    {
        $modelDir = $this->resolvePath((string) config('ekg.deep_learning.model_dir', ''));
        $script = $this->resolvePath((string) config('ekg.deep_learning.predict_script', ''));
        $python = (string) config('ekg.deep_learning.python_binary', 'python');
        $weights = (string) config('ekg.deep_learning.weights_file', 'best_dl_modelExp3LSTMTuned.pth');

        $checks = [
            ['Model aktif', (bool) config('ekg.deep_learning.enabled', true), 'DL_MODEL_ENABLED'],
            ['Folder pipeline', $modelDir !== '' && is_dir($modelDir), $modelDir ?: '(kosong)'],
            ['File bobot', $modelDir !== '' && is_file($modelDir.DIRECTORY_SEPARATOR.$weights), $weights],
            ['Script adapter', $script !== '' && is_file($script), $script ?: '(kosong)'],
        ];

        $failed = false;
        foreach ($checks as [$name, $passed, $detail]) {
            $this->line(sprintf('%s %-18s %s', $passed ? '<info>OK</info>' : '<error>GAGAL</error>', $name, $detail));
            $failed = $failed || ! $passed;
        }

        try {
            $dependencyCheck = Process::timeout(max((int) config('ekg.deep_learning.timeout', 180), 30))->run([
                $python,
                '-c',
                'import torch, numpy, scipy, neurokit2; print("Python dependency model tersedia")',
            ]);
        } catch (Throwable $exception) {
            $this->line(sprintf('<error>GAGAL</error> %-18s %s', 'Dependency Python', $exception->getMessage()));
            $this->error('Model belum siap. Periksa interpreter Python dan dependency model.');

            return self::FAILURE;
        }
        $this->line(sprintf(
            '%s %-18s %s',
            $dependencyCheck->successful() ? '<info>OK</info>' : '<error>GAGAL</error>',
            'Dependency Python',
            trim($dependencyCheck->successful() ? $dependencyCheck->output() : $dependencyCheck->errorOutput())
        ));
        $failed = $failed || ! $dependencyCheck->successful();

        if ($failed) {
            $this->newLine();
            $this->error('Model belum siap. Perbaiki item GAGAL, lalu jalankan php artisan config:cache.');

            return self::FAILURE;
        }

        if ($this->option('run')) {
            $sampleRate = max((int) config('ekg.sample_rate', 250), 1);
            $sampleCount = max((int) config('ekg.minimum_samples', 5000), 2);
            $signal = [];
            for ($index = 0; $index < $sampleCount; $index++) {
                $time = $index / $sampleRate;
                $signal[] = sin(2 * M_PI * 1.2 * $time) + 0.15 * sin(2 * M_PI * 2.4 * $time);
            }

            $result = $classifier->classify($signal, $sampleRate);
            if ($result['label'] === 'PENDING_MODEL') {
                $this->error('Inferensi uji gagal: '.$result['error_message']);

                return self::FAILURE;
            }

            $this->info("Inferensi uji berhasil: {$result['label']}.");
        }

        return self::SUCCESS;
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
