<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\DeepLearningEcgClassificationService;
use Illuminate\Console\Command;

class RetryPendingPredictionsCommand extends Command
{
    protected $signature = 'ekg:retry-pending
        {--session= : Proses hanya satu ID sesi rekaman}
        {--limit=100 : Jumlah maksimal prediksi yang diproses; 0 hanya untuk pemeriksaan}
        {--dry-run : Tampilkan jumlah data tanpa menjalankan prediksi}';

    protected $description = 'Menjalankan ulang prediksi PENDING_MODEL dari sinyal EKG yang sudah tersimpan';

    public function handle(DeepLearningEcgClassificationService $classifier): int
    {
        $minimumSamples = max((int) config('ekg.minimum_samples', 5000), 2);
        $limit = max((int) $this->option('limit'), 0);

        $query = Prediction::query()
            ->where('label', 'PENDING_MODEL')
            ->whereHas('recordingSession.rawSignal', fn ($query) => $query->where('total_samples', '>=', $minimumSamples))
            ->with('recordingSession.rawSignal')
            ->orderBy('id');

        if ($sessionId = $this->option('session')) {
            $query->where('recording_session_id', $sessionId);
        }

        $available = (clone $query)->count();
        $this->info("Ditemukan {$available} prediksi pending dengan minimal {$minimumSamples} sampel.");

        if ($this->option('dry-run') || $limit === 0 || $available === 0) {
            return self::SUCCESS;
        }

        $processed = 0;
        $successful = 0;

        foreach ($query->limit($limit)->get() as $prediction) {
            $rawSignal = $prediction->recordingSession?->rawSignal;
            if (! $rawSignal) {
                continue;
            }

            $result = $classifier->classify(
                array_map('floatval', $rawSignal->voltage_values ?? []),
                (int) $rawSignal->sample_rate
            );

            $prediction->update([
                'label' => $result['label'],
                'confidence' => $result['confidence'],
                'model_version' => $result['model_version'],
                'error_message' => $result['error_message'],
                'predicted_at' => now(),
            ]);

            $processed++;
            $successful += $result['label'] !== 'PENDING_MODEL' ? 1 : 0;
            $this->line("Sesi {$prediction->recording_session_id}: {$result['label']}".($result['error_message'] ? " - {$result['error_message']}" : ''));
        }

        $this->newLine();
        $this->info("Selesai: {$successful} berhasil dari {$processed} data yang diproses.");

        return $successful === $processed ? self::SUCCESS : self::FAILURE;
    }
}
