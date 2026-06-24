<?php

namespace App\Services;

use App\Models\RecordEkg;
use Illuminate\Support\Facades\Process;

class EkgUploadProcessor
{
    public function process(string $datasetPath, RecordEkg $patient): array
    {
        $python = (string) env('EKG_PYTHON_BIN', 'python');
        $timeout = (int) env('EKG_PROCESS_TIMEOUT', 180);
        $processorPath = (string) (env('EKG_PROCESSOR_PATH') ?: base_path('scripts'));

        $result = Process::path(base_path())
            ->timeout($timeout)
            ->env([
                'MPLBACKEND' => 'Agg',
                'MQTT_HOST' => env('MQTT_HOST', '160.187.144.147'),
                'MQTT_PORT' => env('MQTT_PORT', '1883'),
            ])
            ->run([
                $python,
                base_path('scripts/process_ekg_upload.py'),
                '--dataset',
                $datasetPath,
                '--subject-id',
                (string) $patient->id,
                '--subject-name',
                (string) $patient->nama,
                '--processor-path',
                $processorPath,
            ]);

        return [
            'ok' => $result->successful(),
            'output' => trim($result->output()),
            'error' => trim($result->errorOutput()),
            'exit_code' => $result->exitCode(),
        ];
    }
}
