<?php

namespace App\Http\Controllers;

use App\Models\DeviceAssignment;
use App\Models\Puskesmas;
use App\Models\RecordingSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class FacilityController extends Controller
{
    public function index(Request $request)
    {
        $facilities = $this->visibleFacilities($request)
            ->withCount([
                'patients',
                'recordingSessions',
                'deviceAssignments as active_devices_count' => fn ($query) => $query
                    ->where('status', 'active')
                    ->whereNull('unassigned_at'),
            ])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('address', 'like', $term)
                        ->orWhere('contact', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(min(max((int) $request->query('per_page', 10), 5), 50))
            ->withQueryString();

        return view('facilities.index', compact('facilities'));
    }

    public function show(Request $request, string $facility)
    {
        $facility = $this->visibleFacilities($request)
            ->withCount(['patients', 'recordingSessions'])
            ->findOrFail($facility);

        $assignments = DeviceAssignment::query()
            ->with('device')
            ->where('puskesmas_id', $facility->id)
            ->latest('assigned_at')
            ->get();

        $sessions = RecordingSession::query()
            ->visibleTo($request->user())
            ->where('recording_sessions.puskesmas_id', $facility->id)
            ->with(['patient', 'puskesmas', 'device', 'feature', 'prediction'])
            ->latest('recorded_at')
            ->paginate(10)
            ->withQueryString();

        return view('facilities.show', compact('facility', 'assignments', 'sessions'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['code'] = strtoupper(trim($data['code']));

        try {
            Puskesmas::query()->create($data);
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', 'Fasilitas kesehatan gagal ditambahkan: '.$exception->getMessage());
        }

        return redirect()->route('facilities.index')->with('success', 'Fasilitas kesehatan berhasil ditambahkan.');
    }

    public function update(Request $request, Puskesmas $facility)
    {
        $data = $this->validatedData($request, $facility->id);
        $data['code'] = strtoupper(trim($data['code']));

        try {
            $facility->update($data);
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', 'Fasilitas kesehatan gagal diperbarui: '.$exception->getMessage());
        }

        return redirect()->route('facilities.show', $facility)->with('success', 'Fasilitas kesehatan berhasil diperbarui.');
    }

    public function destroy(Puskesmas $facility)
    {
        $isUsed = $facility->patients()->exists()
            || $facility->recordingSessions()->exists()
            || $facility->deviceAssignments()->exists()
            || User::query()->where('puskesmas_id', $facility->id)->exists();

        if ($isUsed) {
            return redirect()->back()->with('error', 'Fasilitas kesehatan tidak dapat dihapus karena sudah memiliki user, pasien, alat, atau sesi rekaman.');
        }

        $facility->delete();

        return redirect()->route('facilities.index')->with('success', 'Fasilitas kesehatan berhasil dihapus.');
    }

    private function visibleFacilities(Request $request)
    {
        return Puskesmas::query()
            ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->whereKey($request->user()->puskesmas_id));
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('puskesmas', 'code')->ignore($ignoreId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
