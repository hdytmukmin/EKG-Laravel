<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Puskesmas;
use App\Support\AccessScope;
use Illuminate\Http\Request;
use Throwable;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $devices = AccessScope::apply(Device::query(), $request->user())
            ->with(['puskesmas'])
            ->withCount('recordingSessions')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->query('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('device_uid', 'like', $term)
                        ->orWhere('mqtt_client_id', 'like', $term)
                        ->orWhereHas('puskesmas', fn ($puskesmas) => $puskesmas->where('name', 'like', $term));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('last_seen_at')
            ->paginate(min(max((int) $request->query('per_page', 10), 5), 50))
            ->withQueryString();

        $puskesmasOptions = $request->user()->isSuperAdmin()
            ? Puskesmas::query()->orderBy('name')->get()
            : Puskesmas::query()->whereKey($request->user()->puskesmas_id)->get();

        return view('devices.index', compact('devices', 'puskesmasOptions'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        try {
            Device::query()->create($this->scopedData($request, $data));
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Alat EKG gagal ditambahkan: '.$exception->getMessage());
        }

        return redirect()->route('devices.index')->with('success', 'Alat EKG berhasil ditambahkan.');
    }

    public function update(Request $request, Device $device)
    {
        $device = AccessScope::apply(Device::query(), $request->user())->findOrFail($device->id);
        $data = $this->validatedData($request, $device->id);

        try {
            $device->update($this->scopedData($request, $data));
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Alat EKG gagal diperbarui: '.$exception->getMessage());
        }

        return redirect()->route('devices.index')->with('success', 'Alat EKG berhasil diperbarui.');
    }

    public function destroy(Request $request, Device $device)
    {
        $device = AccessScope::apply(Device::query(), $request->user())->findOrFail($device->id);

        if ($device->recordingSessions()->exists()) {
            return redirect()->back()->with('error', 'Alat EKG tidak bisa dihapus karena sudah memiliki sesi rekaman.');
        }

        $device->delete();

        return redirect()->route('devices.index')->with('success', 'Alat EKG berhasil dihapus.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'puskesmas_id' => ['nullable', 'integer', 'exists:puskesmas,id'],
            'name' => ['required', 'string', 'max:255'],
            'device_uid' => ['required', 'string', 'max:255', 'unique:devices,device_uid'.($ignoreId ? ','.$ignoreId : '')],
            'mqtt_client_id' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:32'],
        ]);
    }

    private function scopedData(Request $request, array $data): array
    {
        $data['puskesmas_id'] = $request->user()->isSuperAdmin()
            ? ($data['puskesmas_id'] ?? $request->user()->puskesmas_id)
            : $request->user()->puskesmas_id;

        return $data;
    }
}
