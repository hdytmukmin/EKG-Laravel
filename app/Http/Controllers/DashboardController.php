<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\RecordingSession;
use App\Support\AccessScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const AF_LABELS = ['AF', 'PERSISTENT_AF', 'PAROXYSMAL_AF'];

    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $sessionQuery = AccessScope::apply(RecordingSession::query(), $user, 'recording_sessions.puskesmas_id');

            $puskesmasBreakdown = $this->puskesmasBreakdown($user);

            $dashboard = [
                'is_super_admin' => $user->isSuperAdmin(),
                'breakdown' => $puskesmasBreakdown,
            ];

            $sessions = (clone $sessionQuery)
                ->with(['patient', 'puskesmas', 'device', 'feature', 'prediction'])
                ->latest('recorded_at')
                ->limit(8)
                ->get();

            $devices = AccessScope::apply(Device::query(), $user)
                ->get();

            $dbError = null;
        } catch (QueryException $exception) {
            $sessions = collect();
            $devices = collect();
            $dashboard = [
                'is_super_admin' => false,
                'breakdown' => collect(),
            ];
            $dbError = $exception->getMessage();
        }

        return view('dashboard.index', compact('sessions', 'devices', 'dashboard', 'dbError'));
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
                    ->whereHas('recordingSessions.prediction', fn ($query) => $query->whereIn('label', self::AF_LABELS))
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
