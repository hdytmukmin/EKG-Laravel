<?php

namespace App\Http\Controllers;

use App\Models\EkgFeature;
use App\Models\EkgRawSignal;
use App\Models\Patient;
use App\Models\Prediction;
use App\Models\RecordingSession;
use App\Services\DeepLearningEcgClassificationService;
use App\Services\EcgSignalProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadController extends Controller
{
    public function store(
        Request $request,
        Patient $patient,
        EcgSignalProcessingService $processor,
        DeepLearningEcgClassificationService $classifier
    ) {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            'sample_rate' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        try {
            $patient = Patient::query()->visibleTo($request->user())->findOrFail($patient->id);
            $sampleRate = (int) $request->input('sample_rate', 360);
            $path = $request->file('file')->storeAs('ekg-uploads', 'ekg-'.$patient->id.'-'.time().'.csv');
            $rawValues = $this->readNumericCsvValues(Storage::path($path));

            if (count($rawValues) < 2) {
                throw new \RuntimeException('File CSV tidak memiliki data numerik EKG yang cukup.');
            }

            $processed = $processor->process($rawValues, $sampleRate);

            $session = RecordingSession::create([
                'puskesmas_id' => $patient->puskesmas_id,
                'patient_id' => $patient->id,
                'recorded_at' => now(),
                'status' => 'completed',
                'source' => 'upload',
                'notes' => 'Import CSV lokal',
            ]);

            EkgRawSignal::create([
                'recording_session_id' => $session->id,
                'voltage_values' => $processor->roundSeries($rawValues, 6),
                'filtered_values' => $processed['filtered_values'],
                'r_peak_indices' => $processed['r_peak_indices'],
                'sample_rate' => $sampleRate,
                'total_samples' => count($rawValues),
            ]);

            $feature = EkgFeature::create([
                'recording_session_id' => $session->id,
                'subject' => $patient->name,
                'interval_pt' => null,
                'bpm' => $processed['bpm'],
                'rr' => $processed['rr'],
                'rr_lokal' => $processed['rr_lokal'],
                'status' => 'uploaded',
                'sdnn' => $processed['sdnn'],
                'sns' => $processed['sns'],
                'heart_rate' => $processed['heart_rate'],
            ]);

            $prediction = $classifier->classify($rawValues, $sampleRate);
            Prediction::create([
                'recording_session_id' => $session->id,
                'label' => $prediction['label'],
                'confidence' => $prediction['confidence'],
                'model_version' => $prediction['model_version'],
                'error_message' => $prediction['error_message'],
                'predicted_at' => now(),
            ]);
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Upload CSV gagal diproses: '.$exception->getMessage());
        }

        return redirect()
            ->route('recordings.show', $session)
            ->with('success', 'CSV berhasil diproses sebagai sesi rekaman baru.');
    }

    /**
     * @return array<int, float>
     */
    private function readNumericCsvValues(string $path): array
    {
        $values = [];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return $values;
        }

        while (($row = fgetcsv($handle)) !== false) {
            foreach (array_reverse($row) as $cell) {
                $cell = trim((string) $cell);
                if (is_numeric($cell)) {
                    $values[] = (float) $cell;
                    break;
                }
            }
        }

        fclose($handle);

        return $values;
    }
}
