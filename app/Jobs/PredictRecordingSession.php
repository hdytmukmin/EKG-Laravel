<?php

namespace App\Jobs;

use App\Models\Prediction;
use App\Models\RecordingSession;
use App\Services\DeepLearningEcgClassificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PredictRecordingSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $recordingSessionId) {}

    public function handle(DeepLearningEcgClassificationService $classifier): void
    {
        $session = RecordingSession::query()
            ->with('rawSignal')
            ->findOrFail($this->recordingSessionId);
        $rawSignal = $session->rawSignal;

        if (! $rawSignal) {
            throw new \RuntimeException('Raw signal sesi rekaman tidak ditemukan.');
        }

        $result = $classifier->classify(
            array_map('floatval', $rawSignal->voltage_values ?? []),
            (int) $rawSignal->sample_rate
        );

        Prediction::query()->updateOrCreate(
            ['recording_session_id' => $session->id],
            [
                'label' => $result['label'],
                'confidence' => $result['confidence'],
                'model_version' => $result['model_version'],
                'error_message' => $result['error_message'],
                'predicted_at' => now(),
            ]
        );
    }

    public function failed(?Throwable $exception): void
    {
        Prediction::query()->updateOrCreate(
            ['recording_session_id' => $this->recordingSessionId],
            [
                'label' => 'PENDING_MODEL',
                'confidence' => null,
                'error_message' => 'Prediksi gagal diproses oleh worker.',
                'predicted_at' => now(),
            ]
        );

        report($exception);
    }
}
