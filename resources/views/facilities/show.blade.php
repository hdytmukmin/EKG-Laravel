@extends('layouts.app')

@section('title', $facility->name)
@section('page_title', $facility->name)
@section('page_subtitle', $facility->code)

@section('content')
    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-4">
            <div class="panel h-100">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Informasi Faskes</h2>
                    @if (auth()->user()->isSuperAdmin())
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editFacility" title="Edit fasilitas"><i class="bi bi-pencil"></i></button>
                    @endif
                </div>
                <div class="panel-body">
                    <dl class="row mb-0">
                        <dt class="col-4 text-secondary">Nama</dt><dd class="col-8">{{ $facility->name }}</dd>
                        <dt class="col-4 text-secondary">Kode</dt><dd class="col-8">{{ $facility->code }}</dd>
                        <dt class="col-4 text-secondary">Alamat</dt><dd class="col-8">{{ $facility->address ?: '-' }}</dd>
                        <dt class="col-4 text-secondary">Kontak</dt><dd class="col-8">{{ $facility->contact ?: '-' }}</dd>
                        <dt class="col-4 text-secondary">Pasien</dt><dd class="col-8">{{ $facility->patients_count }}</dd>
                        <dt class="col-4 text-secondary">Sesi</dt><dd class="col-8">{{ $facility->recording_sessions_count }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="panel h-100">
                <div class="panel-header"><h2 class="h5 fw-bold mb-0">Riwayat Penggunaan Alat</h2></div>
                <div class="panel-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead><tr><th>Alat</th><th>Device UID</th><th>Mulai</th><th>Selesai</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($assignments as $assignment)
                                <tr>
                                    <td>{{ $assignment->device?->name ?? '-' }}</td>
                                    <td>{{ $assignment->device?->device_uid ?? '-' }}</td>
                                    <td>{{ $assignment->assigned_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>{{ $assignment->unassigned_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td><span class="badge {{ $assignment->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $assignment->status === 'active' ? 'Aktif' : 'Selesai' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="empty-state">Belum ada alat yang ditugaskan.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2 class="h5 fw-bold mb-0">Riwayat Sesi</h2></div>
        <div class="panel-body">
            @include('recordings._sessions-table', ['sessions' => $sessions])
        </div>
    </div>

    @if (auth()->user()->isSuperAdmin())
        <div class="modal fade" id="editFacility" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" action="{{ route('facilities.update', $facility) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h2 class="modal-title h5">Edit Fasilitas Kesehatan</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body row g-3">
                        @include('facilities._form', ['facility' => $facility])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
