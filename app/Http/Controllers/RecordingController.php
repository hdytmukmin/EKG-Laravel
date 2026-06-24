<?php

namespace App\Http\Controllers;

use App\Models\RecordEkg;
use App\Services\EkgPredictionService;
use App\Support\RecordEkgService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Throwable;

class RecordingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $records = RecordEkgService::filteredQuery($request)
                ->paginate(RecordEkgService::perPage($request))
                ->withQueryString();
            $dashboard = RecordEkgService::dashboard();
            $dbError = null;
        } catch (QueryException $exception) {
            $records = collect();
            $dashboard = ['total_patients' => 0, 'completed_records' => 0, 'avg_bpm' => 0, 'latest_record' => '-', 'chart_labels' => [], 'chart_bpm' => []];
            $dbError = $exception->getMessage();
        }

        return view('recordings.index', compact('records', 'dashboard', 'dbError'));
    }

    public function show(string $recording, EkgPredictionService $predictionService)
    {
        try {
            $recording = RecordEkg::query()->findOrFail($recording);
        } catch (Throwable $exception) {
            return redirect()
                ->route('recordings.index')
                ->with('error', 'Detail rekaman belum bisa dibuka: '.$exception->getMessage());
        }

        $predictionResult = $predictionService->predict($recording);
        $prediction = $predictionResult['prediction'];
        $predictionError = $predictionResult['error'];

        return view('recordings.show', compact('recording', 'prediction', 'predictionError'));
    }
}
