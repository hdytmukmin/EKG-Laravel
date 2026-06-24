<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\RecordingSession;
use App\Support\AccessScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Throwable;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = AccessScope::apply(Patient::query(), $request->user())
                ->withCount('recordingSessions')
                ->with('puskesmas')
                ->latest();

            if ($request->filled('q')) {
                $search = trim((string) $request->query('q'));
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('external_subject_id', 'like', "%{$search}%");
                });
            }

            $patients = $query
                ->paginate((int) $request->query('per_page', 10))
                ->withQueryString();
            $dbError = null;
        } catch (QueryException $exception) {
            $patients = collect();
            $dbError = $exception->getMessage();
        }

        return view('patients.index', compact('patients', 'dbError'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'gender' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $user = $request->user();
            $puskesmasId = $user->isSuperAdmin() ? ($request->integer('puskesmas_id') ?: 1) : $user->puskesmas_id;

            Patient::query()->create([
                'puskesmas_id' => $puskesmasId,
                'name' => $data['name'],
                'age' => $data['age'] ?? null,
                'gender' => $data['gender'] ?? null,
                'address' => $data['address'] ?? null,
            ]);
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Data pasien gagal disimpan: '.$exception->getMessage());
        }

        return redirect()->route('patients.index')->with('success', 'Pasien berhasil ditambahkan.');
    }

    public function show(Request $request, string $patient)
    {
        $patient = AccessScope::apply(Patient::query(), $request->user())
            ->with(['puskesmas', 'recordingSessions.feature', 'recordingSessions.prediction', 'recordingSessions.rawSignal'])
            ->findOrFail($patient);

        $sessions = $patient->recordingSessions->sortByDesc('recorded_at')->values();

        return view('patients.show', compact('patient', 'sessions'));
    }

    public function update(Request $request, string $patient)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'gender' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $record = AccessScope::apply(Patient::query(), $request->user())->findOrFail($patient);
            $record->update([
                'name' => $data['name'],
                'age' => $data['age'] ?? null,
                'gender' => $data['gender'] ?? null,
                'address' => $data['address'] ?? null,
            ]);
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Data pasien gagal diperbarui: '.$exception->getMessage());
        }

        return redirect()->back()->with('success', 'Data pasien berhasil diperbarui.');
    }

    public function destroy(Request $request, string $patient)
    {
        try {
            AccessScope::apply(Patient::query(), $request->user())->findOrFail($patient)->delete();
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Data pasien gagal dihapus: '.$exception->getMessage());
        }

        return redirect()->route('patients.index')->with('success', 'Data pasien berhasil dihapus.');
    }

    public function bpmTrend(Request $request, string $patient)
    {
        $patient = AccessScope::apply(Patient::query(), $request->user())
            ->with('recordingSessions.feature')
            ->findOrFail($patient);

        return response()->json([
            'labels' => $patient->recordingSessions
                ->sortBy('recorded_at')
                ->map(fn (RecordingSession $session) => $session->recorded_at?->format('Y-m-d H:i') ?? (string) $session->id)
                ->values(),
            'bpm' => $patient->recordingSessions
                ->sortBy('recorded_at')
                ->map(fn (RecordingSession $session) => $session->feature?->bpm)
                ->values(),
        ]);
    }
}
