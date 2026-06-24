@extends('layouts.app')

@section('title', 'Alat EKG')
@section('page_title', 'Alat EKG')
@section('page_subtitle', 'Manajemen perangkat IoT EKG per puskesmas')

@section('content')
    <div class="panel">
        <div class="panel-header">
            <h2 class="h5 fw-bold mb-0">Daftar Alat</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDevice">
                <i class="bi bi-plus-lg me-1"></i>Tambah
            </button>
        </div>
        <div class="panel-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-lg-5">
                    <label class="form-label small text-secondary">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Nama alat, UID, client ID, puskesmas">
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label small text-secondary">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        @foreach (['online', 'offline', 'unknown', 'maintenance'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small text-secondary">Tampil</label>
                    <select name="per_page" class="form-select">
                        @foreach ([5, 10, 25, 50] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 10) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-grid">
                    <button class="btn btn-primary" type="submit" title="Terapkan filter"><i class="bi bi-funnel"></i></button>
                </div>
            </form>

            <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Alat</th>
                            <th>Puskesmas</th>
                            <th>Client MQTT</th>
                            <th>Status</th>
                            <th>Last Seen</th>
                            <th>Sesi</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($devices as $device)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $device->name }}</div>
                                    <div class="small text-secondary">{{ $device->device_uid }}</div>
                                </td>
                                <td>{{ $device->puskesmas?->name ?? '-' }}</td>
                                <td>{{ $device->mqtt_client_id ?: '-' }}</td>
                                <td>
                                    <span class="badge {{ $device->status === 'online' ? 'text-bg-success' : ($device->status === 'maintenance' ? 'text-bg-warning' : 'text-bg-secondary') }}">
                                        {{ ucfirst($device->status) }}
                                    </span>
                                </td>
                                <td>{{ $device->last_seen_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                <td>{{ $device->recording_sessions_count }}</td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editDevice{{ $device->id }}" title="Edit alat"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" action="{{ route('devices.destroy', $device) }}" onsubmit="return confirm('Hapus alat ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger rounded-start-0" title="Hapus alat"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state">Belum ada alat sesuai filter.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('partials.pagination', ['records' => $devices])
        </div>
    </div>

    <div class="modal fade" id="createDevice" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" action="{{ route('devices.store') }}">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h5">Tambah Alat EKG</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body row g-3">
                    @include('devices._form', ['device' => null])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>

    @foreach ($devices as $device)
        <div class="modal fade" id="editDevice{{ $device->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('devices.update', $device) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h2 class="modal-title h5">Edit Alat EKG</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body row g-3">
                        @include('devices._form', ['device' => $device])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
