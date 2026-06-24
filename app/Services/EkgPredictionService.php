<?php

namespace App\Services;

use App\Models\RecordEkg;
use Illuminate\Support\Facades\Process;

class EkgPredictionService
{
    public function predict(RecordEkg $record): array
    {
        if (! $record->isComplete()) {
            return [
                'ok' => false,
                'prediction' => 'Data belum lengkap',
                'error' => null,
            ];
        }

        $python = (string) env('EKG_PYTHON_BIN', 'python');
        $model = (string) (env('EKG_MODEL_PATH') ?: base_path('scripts/randomforesthari3.pkl'));
        $timeout = (int) env('EKG_PREDICTION_TIMEOUT', 60);

        $result = Process::path(base_path())
            ->timeout($timeout)
            ->env(['PYTHONWARNINGS' => 'ignore'])
            ->run([
                $python,
                base_path('scripts/predict_record.py'),
                '--model',
                $model,
                '--tspt',
                (string) $record->tspt,
                '--bpm',
                (string) $record->bpm,
                '--irr',
                (string) $record->irr,
                '--irrlokal',
                (string) $record->irrlokal,
            ]);

        if (! $result->successful()) {
            $rawError = trim($result->errorOutput()) ?: trim($result->output());
            logger()->warning('EKG prediction failed', [
                'record_id' => $record->id,
                'model' => $model,
                'error' => $rawError,
            ]);

            return [
                'ok' => false,
                'prediction' => 'Prediksi belum tersedia',
                'error' => $this->friendlyError($rawError),
            ];
        }

        $decoded = json_decode(trim($result->output()), true);

        return [
            'ok' => true,
            'prediction' => $decoded['prediction'] ?? trim($result->output()),
            'error' => null,
        ];
    }

    private function friendlyError(string $error): string
    {
        $lower = strtolower($error);

        if (str_contains($lower, 'inconsistentversionwarning')
            || str_contains($lower, 'incompatible dtype')
            || str_contains($lower, 'node array from the pickle')) {
            return 'Model ML lama belum kompatibel dengan environment Python lokal. Gunakan environment VPS/venv yang sesuai dengan versi scikit-learn model.';
        }

        if (str_contains($lower, 'no such file') || str_contains($lower, 'filenotfounderror')) {
            return 'File model prediksi belum ditemukan. Periksa EKG_MODEL_PATH atau file scripts/randomforesthari3.pkl.';
        }

        if (str_contains($lower, 'modulenotfounderror') || str_contains($lower, 'no module named')) {
            return 'Dependency Python untuk prediksi belum lengkap. Periksa EKG_PYTHON_BIN dan paket Python di environment tersebut.';
        }

        if (str_contains($lower, 'timed out')) {
            return 'Proses prediksi melewati batas waktu. Coba ulang atau naikkan EKG_PREDICTION_TIMEOUT.';
        }

        if ($error === '') {
            return 'Proses prediksi gagal tanpa pesan error dari Python.';
        }

        return 'Proses prediksi belum berhasil. Detail teknis tersimpan di log Laravel.';
    }
}
