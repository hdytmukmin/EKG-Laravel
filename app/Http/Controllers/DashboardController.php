<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Patient;
use App\Models\Prediction;
use App\Models\Puskesmas;
use App\Models\RecordingSession;
use App\Support\AccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $puskesmasId = $user->isSuperAdmin() ? $request->integer('puskesmas_id') : $user->puskesmas_id;
            $deviceId = $request->integer('device_id');

            $patientQuery = AccessScope::apply(Patient::query(), $user)
                ->when($puskesmasId, fn ($query) => $query->where('puskesmas_id', $puskesmasId));

            $sessionQuery = AccessScope::apply(RecordingSession::query(), $user, 'recording_sessions.puskesmas_id')
                ->when($puskesmasId, fn ($query) => $query->where('recording_sessions.puskesmas_id', $puskesmasId))
                ->when($deviceId, fn ($query) => $query->where('device_id', $deviceId));

            $predictionQuery = Prediction::query()->whereHas('recordingSession', function (Builder $query) use ($user, $puskesmasId, $deviceId) {
                AccessScope::apply($query, $user, 'recording_sessions.puskesmas_id')
                    ->when($puskesmasId, fn ($inner) => $inner->where('recording_sessions.puskesmas_id', $puskesmasId))
                    ->when($deviceId, fn ($inner) => $inner->where('device_id', $deviceId));
            });

            $chartSessions = (clone $sessionQuery)
                ->with(['patient', 'puskesmas', 'device'])
                ->latest('recorded_at')
                ->limit(30)
                ->get();

            $selectedSessionId = $request->integer('session_id');
            $chartSession = $selectedSessionId
                ? (clone $sessionQuery)->with(['patient', 'device', 'feature', 'rawSignal', 'prediction'])->whereKey($selectedSessionId)->first()
                : null;

            $chartSession ??= (clone $sessionQuery)
                ->with(['patient', 'device', 'feature', 'rawSignal', 'prediction'])
                ->latest('recorded_at')
                ->first();

            $puskesmasBreakdown = $this->puskesmasBreakdown($user);

            $dashboard = [
                'total_patients' => (clone $patientQuery)->count(),
                'total_af' => (clone $patientQuery)->whereHas('recordingSessions.prediction', fn ($query) => $query->where('label', 'AF'))->count(),
                'total_non_af' => (clone $patientQuery)->whereHas('recordingSessions.prediction', fn ($query) => $query->where('label', 'NON_AF'))->count(),
                'latest_session' => (clone $sessionQuery)->with(['patient', 'device', 'feature', 'rawSignal', 'prediction'])->latest('recorded_at')->first(),
                'chart_session' => $chartSession,
                'chart_sessions' => $chartSessions,
                'is_super_admin' => $user->isSuperAdmin(),
                'breakdown' => $puskesmasBreakdown,
                'comparison_labels' => $puskesmasBreakdown->pluck('name')->values(),
                'comparison_af' => $puskesmasBreakdown->pluck('af_patients')->values(),
                'comparison_non_af' => $puskesmasBreakdown->pluck('non_af_patients')->values(),
            ];

            $sessions = (clone $sessionQuery)
                ->with(['patient', 'puskesmas', 'device', 'feature', 'prediction'])
                ->latest('recorded_at')
                ->limit(8)
                ->get();

            $devices = AccessScope::apply(Device::query(), $user)
                ->with('puskesmas')
                ->when($puskesmasId, fn ($query) => $query->where('puskesmas_id', $puskesmasId))
                ->orderBy('name')
                ->get();

            $puskesmasOptions = $user->isSuperAdmin()
                ? Puskesmas::query()->orderBy('name')->get()
                : Puskesmas::query()->whereKey($user->puskesmas_id)->get();

            $dbError = null;
        } catch (QueryException $exception) {
            $sessions = collect();
            $devices = collect();
            $puskesmasOptions = collect();
            $dashboard = [
                'total_patients' => 0,
                'total_af' => 0,
                'total_non_af' => 0,
                'latest_session' => null,
                'chart_session' => null,
                'chart_sessions' => collect(),
                'is_super_admin' => false,
                'breakdown' => collect(),
                'comparison_labels' => collect(),
                'comparison_af' => collect(),
                'comparison_non_af' => collect(),
            ];
            $dbError = $exception->getMessage();
        }

        return view('dashboard.index', compact('sessions', 'devices', 'puskesmasOptions', 'dashboard', 'dbError'));
    }

    private function puskesmasBreakdown($user)
    {
        return AccessScope::apply(Puskesmas::query(), $user, 'id')
            ->withCount(['patients', 'recordingSessions'])
            ->orderBy('name')
            ->get()
            ->map(function (Puskesmas $puskesmas) {
                $afPatients = Patient::query()
                    ->where('puskesmas_id', $puskesmas->id)
                    ->whereHas('recordingSessions.prediction', fn ($query) => $query->where('label', 'AF'))
                    ->count();

                $nonAfPatients = Patient::query()
                    ->where('puskesmas_id', $puskesmas->id)
                    ->whereHas('recordingSessions.prediction', fn ($query) => $query->where('label', 'NON_AF'))
                    ->count();

                $latestSession = RecordingSession::query()
                    ->where('puskesmas_id', $puskesmas->id)
                    ->latest('recorded_at')
                    ->first();

                $puskesmas->af_patients = $afPatients;
                $puskesmas->non_af_patients = $nonAfPatients;
                $puskesmas->latest_recorded_at = $latestSession?->recorded_at;

                return $puskesmas;
            });
    }
}
