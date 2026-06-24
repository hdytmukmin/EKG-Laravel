<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\RecordingSession;
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

            $dbError = null;
        } catch (QueryException $exception) {
            $devices = collect();
            $latest = null;
            $sessions = collect();
            $dbError = $exception->getMessage();
        }

        return view('monitoring.index', compact('devices', 'latest', 'sessions', 'dbError'));
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
                    'status' => $device->status,
                    'last_seen_at' => $device->last_seen_at?->format('Y-m-d H:i:s'),
                ])->values(),
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
