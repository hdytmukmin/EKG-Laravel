<?php

namespace App\Http\Controllers;

use App\Models\RecordEkg;
use App\Support\RecordEkgService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class MonitoringController extends Controller
{
    public function index()
    {
        try {
            $dashboard = RecordEkgService::dashboard();
            $latest = RecordEkg::query()
                ->whereNotIn('tspt', ['', '0', '0.0'])
                ->whereNotIn('bpm', ['', '0', '0.0'])
                ->orderByDesc('id')
                ->first() ?? RecordEkg::query()->orderByDesc('id')->first();
            $recentRows = RecordEkg::query()->orderByDesc('id')->limit(10)->get();
            $dbError = null;
        } catch (QueryException $exception) {
            $dashboard = ['total_patients' => 0, 'completed_records' => 0, 'avg_bpm' => 0, 'latest_record' => '-', 'chart_labels' => [], 'chart_bpm' => []];
            $latest = null;
            $recentRows = collect();
            $dbError = $exception->getMessage();
        }

        return view('monitoring.index', compact('dashboard', 'latest', 'recentRows', 'dbError'));
    }

    public function latest(): JsonResponse
    {
        try {
            $dashboard = RecordEkgService::dashboard();
            $latest = RecordEkg::query()
                ->whereNotIn('tspt', ['', '0', '0.0'])
                ->whereNotIn('bpm', ['', '0', '0.0'])
                ->orderByDesc('id')
                ->first() ?? RecordEkg::query()->orderByDesc('id')->first();

            return response()->json([
                'ok' => true,
                'database' => 'connected',
                'dashboard' => $dashboard,
                'latest' => $latest ? [
                    'id' => $latest->id,
                    'nama' => $latest->nama,
                    'tglrekam' => $latest->tglrekam,
                    'tspt' => $latest->tspt,
                    'bpm' => $latest->bpm,
                    'irr' => $latest->irr,
                    'irrlokal' => $latest->irrlokal,
                    'status' => $latest->status(),
                    'heart_rate' => $latest->heartRateSeries(),
                ] : null,
                'checked_at' => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (QueryException $exception) {
            return response()->json([
                'ok' => false,
                'database' => 'error',
                'message' => $exception->getMessage(),
                'checked_at' => now()->format('Y-m-d H:i:s'),
            ], 503);
        }
    }
}
