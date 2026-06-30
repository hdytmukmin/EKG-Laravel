<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\RecordingSession;
use App\Support\AccessScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordingController extends Controller
{
    private const AF_LABELS = ['AF', 'PERSISTENT_AF', 'PAROXYSMAL_AF'];

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $puskesmasId = $user->isSuperAdmin() ? $request->integer('puskesmas_id') : $user->puskesmas_id;
            $deviceId = $request->integer('device_id');
            $patientId = $request->integer('patient_id');

            $query = RecordingSession::query()
                ->with(['patient.puskesmas', 'puskesmas', 'device', 'feature', 'prediction'])
                ->visibleTo($user)
                ->when($puskesmasId, fn ($query) => $query->where('recording_sessions.puskesmas_id', $puskesmasId))
                ->when($deviceId, fn ($query) => $query->where('device_id', $deviceId))
                ->when($patientId, fn ($query) => $query->where('patient_id', $patientId))
                ->when($request->filled('recorded_from'), fn ($query) => $query->whereDate('recorded_at', '>=', $request->query('recorded_from')))
                ->when($request->filled('recorded_to'), fn ($query) => $query->whereDate('recorded_at', '<=', $request->query('recorded_to')))
                ->when($request->filled('q'), function ($query) use ($request) {
                    $term = '%'.$request->query('q').'%';
                    $query->where(function ($inner) use ($term) {
                        $inner->whereHas('patient', fn ($patient) => $patient->where('name', 'like', $term))
                            ->orWhereHas('puskesmas', fn ($puskesmas) => $puskesmas->where('name', 'like', $term))
                            ->orWhereHas('device', fn ($device) => $device->where('name', 'like', $term));
                    });
                })
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
                ->when($request->filled('prediction'), function ($query) use ($request) {
                    $predictionLabel = $request->query('prediction');

                    $query->whereHas('prediction', fn ($prediction) => $predictionLabel === 'AF'
                        ? $prediction->whereIn('label', self::AF_LABELS)
                        : $prediction->where('label', $predictionLabel));
                })
                ->latest('recorded_at');

            $sessions = $query
                ->paginate(min(max((int) $request->query('per_page', 10), 5), 50))
                ->withQueryString();

            $base = (clone $query)->reorder();
            $summary = [
                'total_sessions' => (clone $base)->count(),
                'total_af' => (clone $base)->whereHas('prediction', fn ($prediction) => $prediction->whereIn('label', self::AF_LABELS))->count(),
                'total_non_af' => (clone $base)->whereHas('prediction', fn ($prediction) => $prediction->where('label', 'NON_AF'))->count(),
            ];

            $puskesmasOptions = $user->isSuperAdmin()
                ? Puskesmas::query()->orderBy('name')->get()
                : Puskesmas::query()->whereKey($user->puskesmas_id)->get();

            $deviceOptions = AccessScope::apply(Device::query(), $user)
                ->when($puskesmasId, fn ($query) => $query->where('puskesmas_id', $puskesmasId))
                ->orderBy('name')
                ->get();

            $patientOptions = AccessScope::apply(Patient::query(), $user)
                ->when($puskesmasId, fn ($query) => $query->where('puskesmas_id', $puskesmasId))
                ->orderBy('name')
                ->get();

            $dbError = null;
        } catch (QueryException $exception) {
            $sessions = collect();
            $summary = ['total_sessions' => 0, 'total_af' => 0, 'total_non_af' => 0];
            $puskesmasOptions = collect();
            $deviceOptions = collect();
            $patientOptions = collect();
            $dbError = $exception->getMessage();
        }

        return view('recordings.index', compact('sessions', 'summary', 'puskesmasOptions', 'deviceOptions', 'patientOptions', 'dbError'));
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
        $filtered = $recording->rawSignal?->filtered_values ?? [];
        $rPeaks = $recording->rawSignal?->r_peak_indices ?? [];
        $sampleRate = $recording->rawSignal?->sample_rate ?: 360;
        $rrSeries = $this->rrSeriesFromPeaks($rPeaks, $sampleRate);
        $bpmSeries = $rrSeries
            ? array_map(fn (array $point) => ['x' => $point['x'], 'y' => $point['y'] > 0 ? 60 / $point['y'] : null], $rrSeries)
            : array_values($heartRate);

        if (! $rrSeries && $recording->feature?->rr !== null) {
            $rrSeries = [['x' => 1, 'y' => (float) $recording->feature->rr]];
        }

        if (! $bpmSeries && $recording->feature?->bpm !== null) {
            $bpmSeries = [['x' => 1, 'y' => (float) $recording->feature->bpm]];
        }

        return response()->json([
            'heart_rate' => $bpmSeries,
            'rr' => $rrSeries,
            'raw_signal' => array_values($raw),
            'filtered_signal' => array_values($filtered),
            'r_peaks' => array_values($rPeaks),
            'sample_rate' => $sampleRate,
        ]);
    }

    private function rrSeriesFromPeaks(array $rPeaks, int $sampleRate): array
    {
        $series = [];

        for ($index = 1; $index < count($rPeaks); $index++) {
            $rr = ($rPeaks[$index] - $rPeaks[$index - 1]) / $sampleRate;
            if ($rr > 0) {
                $series[] = [
                    'x' => $rPeaks[$index] / $sampleRate,
                    'y' => $rr,
                ];
            }
        }

        return $series;
    }
}
