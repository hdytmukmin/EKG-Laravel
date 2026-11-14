<div class="col-12">
    @php($selectedFacilities = collect(old('puskesmas_ids', $device?->facilities?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id))
    <label class="form-label">Fasilitas Kesehatan yang Diizinkan</label>
    <select name="puskesmas_ids[]" class="form-select" multiple size="4" required>
        @foreach ($puskesmasOptions as $puskesmas)
            <option value="{{ $puskesmas->id }}" @selected($selectedFacilities->contains($puskesmas->id))>
                {{ $puskesmas->name }} ({{ $puskesmas->code }})
            </option>
        @endforeach
    </select>
    <div class="form-text">Gunakan Ctrl atau Command untuk memilih lebih dari satu fasilitas.</div>
</div>
<div class="col-12">
    <label class="form-label">Nama Alat</label>
    <input name="name" class="form-control" value="{{ old('name', $device?->name) }}" required>
</div>
<div class="col-12">
    <label class="form-label">Device UID</label>
    <input name="device_uid" class="form-control text-uppercase" value="{{ old('device_uid', $device?->device_uid) }}" placeholder="EKG-DEVICE-001" maxlength="64" required>
</div>
<div class="col-12">
    <label class="form-label">MQTT Client ID</label>
    <input name="mqtt_client_id" class="form-control" value="{{ old('mqtt_client_id', $device?->mqtt_client_id) }}">
</div>
<div class="col-12">
    <label class="form-label">Mode Operasional</label>
    <select name="operational_mode" class="form-select" required>
        <option value="normal" @selected(old('operational_mode', $device?->status === 'maintenance' ? 'maintenance' : 'normal') === 'normal')>Normal</option>
        <option value="maintenance" @selected(old('operational_mode', $device?->status) === 'maintenance')>Pemeliharaan</option>
    </select>
</div>
