<?php

namespace App\Http\Controllers;

use App\Models\RecordEkg;
use App\Services\EkgUploadProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadController extends Controller
{
    public function store(Request $request, string $patient, EkgUploadProcessor $processor)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        try {
            $record = RecordEkg::query()->findOrFail($patient);
            $path = $request->file('file')->storeAs('ekg-uploads', 'DataSetFile-'.$record->id.'-'.time().'.csv');
            $result = $processor->process(Storage::path($path), $record);
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Upload CSV gagal diproses: '.$exception->getMessage());
        }

        if (! $result['ok']) {
            return redirect()
                ->back()
                ->with('error', 'CSV berhasil diupload, tetapi proses EKG gagal. Exit code: '.$result['exit_code'].'. '.$result['error']);
        }

        return redirect()
            ->back()
            ->with('success', 'CSV berhasil diproses. Hasil akan masuk melalui alur MQTT dan tampil di dashboard.');
    }
}
