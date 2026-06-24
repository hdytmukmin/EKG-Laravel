<?php

namespace App\Http\Controllers;

use App\Models\EkgFeature;
use App\Models\EkgRawSignal;
use App\Models\Patient;
use App\Models\Prediction;
use App\Models\RecordingSession;
use App\Services\AfClassificationService;
use App\Services\SdnnSnsExtractorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadController extends Controller
{
    public function store(
        Request $request,
        Patient $patient,
        SdnnSnsExtractorService $extractor,
        AfClassificationService $classifier
    ) {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        try {
            $patient = Patient::query()->visibleTo($request->user())->findOrFail($patient->id);
            $path = $request->file('file')->storeAs('ekg-uploads', 'ekg-'.$patient->id.'-'.time().'.csv');
            $rawValues = $this->readNumericCsvValues(Storage::path($path));

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
                'voltage_values' => $rawValues,
                'sample_rate' => null,
                'total_samples' => count($rawValues),
            ]);

            $extracted = $extractor->extract($rawValues);
            $feature = EkgFeature::create([
                'recording_session_id' => $session->id,
                'subject' => $patient->name,
                'status' => 'uploaded',
                'sdnn' => $extracted['sdnn'],
                'sns' => $extracted['sns'],
                'heart_rate' => [],
            ]);

            $prediction = $classifier->classify($feature);
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
