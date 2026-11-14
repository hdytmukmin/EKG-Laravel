<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\Puskesmas;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $devices = Device::query()
            ->visibleTo($request->user())
            ->with(['puskesmas', 'facilities'])
            ->withCount('recordingSessions')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->query('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('device_uid', 'like', $term)
                        ->orWhere('mqtt_client_id', 'like', $term)
                        ->orWhereHas('facilities', fn ($facility) => $facility->where('name', 'like', $term))
                        ->orWhereHas('puskesmas', fn ($facility) => $facility->where('name', 'like', $term));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('last_seen_at')
            ->paginate(min(max((int) $request->query('per_page', 10), 5), 50))
            ->withQueryString();

        $puskesmasOptions = Puskesmas::query()->orderBy('name')->get();

        return view('devices.index', compact('devices', 'puskesmasOptions'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $facilityIds = collect($data['puskesmas_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $data['device_uid'] = strtoupper(trim($data['device_uid']));
        $data['status'] = $data['operational_mode'] === 'maintenance' ? 'maintenance' : 'unknown';

        try {
            DB::transaction(function () use ($data, $facilityIds): void {
                $device = Device::query()->create([
                    ...Arr::except($data, ['puskesmas_ids', 'operational_mode']),
                    'puskesmas_id' => $facilityIds->first(),
                ]);

                $this->syncAssignments($device, $facilityIds->all());
            });
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Alat EKG gagal ditambahkan: '.$exception->getMessage());
        }

        return redirect()->route('devices.index')->with('success', 'Alat EKG berhasil ditambahkan.');
    }

    public function update(Request $request, Device $device)
    {
        $device = Device::query()->findOrFail($device->id);
        $data = $this->validatedData($request, $device->id);
        $facilityIds = collect($data['puskesmas_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $normalizedUid = strtoupper(trim($data['device_uid']));

        if ($normalizedUid !== $device->device_uid && $device->recordingSessions()->exists()) {
            throw ValidationException::withMessages([
                'device_uid' => 'Device UID tidak dapat diubah karena alat sudah memiliki sesi rekaman.',
            ]);
        }

        $data['device_uid'] = $normalizedUid;
        $data['status'] = $data['operational_mode'] === 'maintenance'
            ? 'maintenance'
            : ($device->status === 'maintenance' ? 'unknown' : $device->status);

        try {
            DB::transaction(function () use ($device, $data, $facilityIds): void {
                $device->update([
                    ...Arr::except($data, ['puskesmas_ids', 'operational_mode']),
                    'puskesmas_id' => $facilityIds->first(),
                ]);

                $this->syncAssignments($device, $facilityIds->all());
            });
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Alat EKG gagal diperbarui: '.$exception->getMessage());
        }

        return redirect()->route('devices.index')->with('success', 'Alat EKG berhasil diperbarui.');
    }

    public function destroy(Request $request, Device $device)
    {
        $device = Device::query()->findOrFail($device->id);

        if ($device->recordingSessions()->exists()) {
            return redirect()->back()->with('error', 'Alat EKG tidak bisa dihapus karena sudah memiliki sesi rekaman.');
        }

        $device->delete();

        return redirect()->route('devices.index')->with('success', 'Alat EKG berhasil dihapus.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'puskesmas_ids' => ['required', 'array', 'min:1'],
            'puskesmas_ids.*' => ['required', 'integer', 'distinct', 'exists:puskesmas,id'],
            'name' => ['required', 'string', 'max:255'],
            'device_uid' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('devices', 'device_uid')->ignore($ignoreId),
            ],
            'mqtt_client_id' => ['nullable', 'string', 'max:255'],
            'operational_mode' => ['required', Rule::in(['normal', 'maintenance'])],
        ]);
    }

    private function syncAssignments(Device $device, array $facilityIds): void
    {
        $activeIds = $device->activeAssignments()->pluck('puskesmas_id');
        $requestedIds = collect($facilityIds);
        $now = now();

        $device->activeAssignments()
            ->whereIn('puskesmas_id', $activeIds->diff($requestedIds))
            ->update([
                'status' => 'inactive',
                'unassigned_at' => $now,
            ]);

        foreach ($requestedIds->diff($activeIds) as $facilityId) {
            DeviceAssignment::query()->create([
                'device_id' => $device->id,
                'puskesmas_id' => $facilityId,
                'assigned_at' => $now,
                'status' => 'active',
            ]);
        }
    }
}
