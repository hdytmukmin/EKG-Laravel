<div class="col-12">
    <label class="form-label">Puskesmas</label>
    <select name="puskesmas_id" class="form-select" @disabled(! auth()->user()->isSuperAdmin())>
        @foreach ($puskesmasOptions as $puskesmas)
            <option value="{{ $puskesmas->id }}" @selected(old('puskesmas_id', $device?->puskesmas_id ?? auth()->user()->puskesmas_id) == $puskesmas->id)>
                {{ $puskesmas->name }}
            </option>
        @endforeach
    </select>
</div>
<div class="col-12">
    <label class="form-label">Nama Alat</label>
    <input name="name" class="form-control" value="{{ old('name', $device?->name) }}" required>
</div>
<div class="col-12">
    <label class="form-label">Device UID</label>
    <input name="device_uid" class="form-control" value="{{ old('device_uid', $device?->device_uid) }}" required>
</div>
<div class="col-12">
    <label class="form-label">MQTT Client ID</label>
    <input name="mqtt_client_id" class="form-control" value="{{ old('mqtt_client_id', $device?->mqtt_client_id) }}">
</div>
<div class="col-12">
    <label class="form-label">Status</label>
    <select name="status" class="form-select" required>
        @foreach (['online', 'offline', 'unknown', 'maintenance'] as $status)
            <option value="{{ $status }}" @selected(old('status', $device?->status ?? 'unknown') === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
</div>
