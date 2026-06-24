<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Patient;
use App\Models\Prediction;
use App\Models\Puskesmas;
use App\Models\RecordingSession;
use App\Support\AccessScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $patientQuery = AccessScope::apply(Patient::query(), $user);
            $sessionQuery = AccessScope::apply(RecordingSession::query(), $user, 'recording_sessions.puskesmas_id');
            $predictionQuery = Prediction::query()->whereHas('recordingSession', fn ($query) => AccessScope::apply($query, $user, 'recording_sessions.puskesmas_id'));

            $dashboard = [
                'total_patients' => (clone $patientQuery)->count(),
                'total_af' => (clone $predictionQuery)->where('label', 'AF')->count(),
                'total_non_af' => (clone $predictionQuery)->where('label', 'NON_AF')->count(),
                'latest_session' => (clone $sessionQuery)->with(['patient', 'device', 'feature', 'rawSignal', 'prediction'])->latest('recorded_at')->first(),
                'is_super_admin' => $user->isSuperAdmin(),
                'breakdown' => $user->isSuperAdmin()
                    ? Puskesmas::query()->withCount([
                        'patients',
                        'recordingSessions',
                    ])->get()
                    : collect(),
            ];

            $sessions = (clone $sessionQuery)
                ->with(['patient', 'puskesmas', 'device', 'feature', 'prediction'])
                ->latest('recorded_at')
                ->limit(8)
                ->get();

            $devices = AccessScope::apply(Device::query(), $user)->with('puskesmas')->get();
            $dbError = null;
        } catch (QueryException $exception) {
            $sessions = collect();
            $devices = collect();
            $dashboard = ['total_patients' => 0, 'total_af' => 0, 'total_non_af' => 0, 'latest_session' => null, 'is_super_admin' => false, 'breakdown' => collect()];
            $dbError = $exception->getMessage();
        }

        return view('dashboard.index', compact('sessions', 'devices', 'dashboard', 'dbError'));
    }
}
