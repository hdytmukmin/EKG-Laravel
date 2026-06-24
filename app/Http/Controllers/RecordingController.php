<?php

namespace App\Http\Controllers;

use App\Models\RecordingSession;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = RecordingSession::query()
                ->with(['patient.puskesmas', 'puskesmas', 'device', 'feature', 'prediction'])
                ->visibleTo($request->user())
                ->when($request->filled('q'), function ($query) use ($request) {
                    $term = '%'.$request->query('q').'%';
                    $query->where(function ($inner) use ($term) {
                        $inner->whereHas('patient', fn ($patient) => $patient->where('name', 'like', $term))
                            ->orWhereHas('puskesmas', fn ($puskesmas) => $puskesmas->where('name', 'like', $term))
                            ->orWhereHas('device', fn ($device) => $device->where('name', 'like', $term));
                    });
                })
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
                ->when($request->filled('prediction'), fn ($query) => $query->whereHas('prediction', fn ($prediction) => $prediction->where('label', $request->query('prediction'))))
                ->latest('recorded_at');

            $sessions = $query
                ->paginate(min(max((int) $request->query('per_page', 10), 5), 50))
                ->withQueryString();

            $base = RecordingSession::query()->visibleTo($request->user());
            $summary = [
                'total_sessions' => (clone $base)->count(),
                'total_af' => (clone $base)->whereHas('prediction', fn ($prediction) => $prediction->where('label', 'AF'))->count(),
                'total_non_af' => (clone $base)->whereHas('prediction', fn ($prediction) => $prediction->where('label', 'NON_AF'))->count(),
            ];
            $dbError = null;
        } catch (QueryException $exception) {
            $sessions = collect();
            $summary = ['total_sessions' => 0, 'total_af' => 0, 'total_non_af' => 0];
            $dbError = $exception->getMessage();
        }

        return view('recordings.index', compact('sessions', 'summary', 'dbError'));
    }

    public function show(Request $request, RecordingSession $recording)
    {
        $recording = RecordingSession::query()
            ->visibleTo($request->user())
            ->with(['patient.puskesmas', 'puskesmas', 'device', 'feature', 'rawSignal', 'prediction'])
            ->findOrFail($recording->id);

        return view('recordings.show', compact('recording'));
    }

    public function chartData(Request $request, RecordingSession $recording): JsonResponse
    {
        $recording = RecordingSession::query()
            ->visibleTo($request->user())
            ->with(['feature', 'rawSignal'])
            ->findOrFail($recording->id);

        $heartRate = $recording->feature?->heart_rate ?? [];
        $raw = $recording->rawSignal?->voltage_values ?? [];

        return response()->json([
            'heart_rate' => array_values($heartRate),
            'rr' => array_fill(0, max(count($heartRate), 1), $recording->feature?->rr),
            'raw_signal' => array_values($raw),
            'sample_rate' => $recording->rawSignal?->sample_rate,
        ]);
    }
}
