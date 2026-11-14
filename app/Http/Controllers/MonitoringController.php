<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Patient;
use App\Models\RecordingSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function index(Request $request)
    {
        try {
            $devices = Device::query()
                ->with('puskesmas')
                ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->where('puskesmas_id', $request->user()->puskesmas_id))
                ->latest('last_seen_at')
                ->get();

            $latest = RecordingSession::query()
                ->visibleTo($request->user())
                ->with(['patient', 'puskesmas', 'device', 'feature', 'prediction'])
                ->latest('recorded_at')
                ->first();

            $sessions = RecordingSession::query()
                ->visibleTo($request->user())
                ->with(['patient', 'puskesmas', 'device', 'feature', 'prediction'])
                ->latest('recorded_at')
                ->limit(10)
                ->get();

            $classification = $this->classificationSummary($request->user());

            $dbError = null;
        } catch (QueryException $exception) {
            $devices = collect();
            $latest = null;
            $sessions = collect();
            $classification = ['af' => 0, 'non_af' => 0, 'unclassified' => 0, 'total' => 0];
            $dbError = $exception->getMessage();
        }

        return view('monitoring.index', compact('devices', 'latest', 'sessions', 'classification', 'dbError'));
    }

    public function latest(Request $request): JsonResponse
    {
        try {
            $latest = RecordingSession::query()
                ->visibleTo($request->user())
                ->with(['patient', 'puskesmas', 'device', 'feature', 'prediction'])
                ->latest('recorded_at')
                ->first();

            $devices = Device::query()
                ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->where('puskesmas_id', $request->user()->puskesmas_id))
                ->get();

            return response()->json([
                'ok' => true,
                'database' => 'connected',
                'latest' => $latest ? [
                    'id' => $latest->id,
                    'patient' => $latest->patient?->name,
                    'puskesmas' => $latest->puskesmas?->name,
                    'recorded_at' => $latest->recorded_at?->format('Y-m-d H:i:s'),
                    'bpm' => $latest->feature?->bpm,
                    'rr' => $latest->feature?->rr,
                    'prediction' => $latest->prediction?->label,
                ] : null,
                'devices' => $devices->map(fn ($device) => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'status' => $device->effectiveStatus(),
                    'status_label' => $device->statusLabel(),
                    'status_class' => $device->statusBadgeClass(),
                    'last_seen_at' => $device->last_seen_at?->format('Y-m-d H:i:s'),
                ])->values(),
                'classification' => $this->classificationSummary($request->user()),
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

    private function classificationSummary(User $user): array
    {
        $labels = Patient::query()
            ->visibleTo($user)
            ->with([
                'latestRecordingSession.prediction',
                'latestRecordingSession.rawSignal:id,recording_session_id,total_samples',
            ])
            ->get()
            ->map(function (Patient $patient) {
                $session = $patient->latestRecordingSession;
                $minimumSamples = max((int) config('ekg.minimum_samples', 5000), 2);

                if (! $session?->rawSignal || $session->rawSignal->total_samples < $minimumSamples) {
                    return null;
                }

                return $session->prediction?->label;
            });

        $afLabels = ['AF', 'PERSISTENT_AF', 'PAROXYSMAL_AF'];
        $af = $labels->filter(fn ($label) => in_array($label, $afLabels, true))->count();
        $nonAf = $labels->filter(fn ($label) => $label === 'NON_AF')->count();

        return [
            'af' => $af,
            'non_af' => $nonAf,
            'unclassified' => $labels->count() - $af - $nonAf,
            'total' => $labels->count(),
        ];
    }
}
