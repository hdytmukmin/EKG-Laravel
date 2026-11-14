@extends('layouts.app')

@section('title', 'Fasilitas Kesehatan')
@section('page_title', 'Fasilitas Kesehatan')
@section('page_subtitle', 'Data fasilitas dan riwayat pemeriksaan')

@section('content')
    <div class="panel">
        <div class="panel-header">
            <h2 class="h5 fw-bold mb-0">Daftar Fasilitas Kesehatan</h2>
            @if (auth()->user()->isSuperAdmin())
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFacility">
                    <i class="bi bi-plus-lg me-1"></i>Tambah
                </button>
            @endif
        </div>
        <div class="panel-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-lg-7">
                    <label class="form-label small text-secondary">Pencarian</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Nama, kode, alamat, atau kontak">
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label small text-secondary">Tampil</label>
                    <select name="per_page" class="form-select">
                        @foreach ([5, 10, 25, 50] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 10) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2 d-grid">
                    <button class="btn btn-primary" type="submit" title="Terapkan filter"><i class="bi bi-funnel"></i></button>
                </div>
            </form>

            <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Fasilitas Kesehatan</th>
                            <th>Alamat</th>
                            <th>Kontak</th>
                            <th>Alat Aktif</th>
                            <th>Pasien</th>
                            <th>Sesi</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($facilities as $facility)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $facility->name }}</div>
                                    <div class="small text-secondary">{{ $facility->code }}</div>
                                </td>
                                <td>{{ $facility->address ?: '-' }}</td>
                                <td>{{ $facility->contact ?: '-' }}</td>
                                <td><span class="badge text-bg-primary">{{ $facility->active_devices_count }}</span></td>
                                <td>{{ $facility->patients_count }}</td>
                                <td>{{ $facility->recording_sessions_count }}</td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('facilities.show', $facility) }}" title="Detail fasilitas"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state">Belum ada fasilitas kesehatan.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('partials.pagination', ['records' => $facilities])
        </div>
    </div>

    @if (auth()->user()->isSuperAdmin())
        <div class="modal fade" id="createFacility" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" action="{{ route('facilities.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5">Tambah Fasilitas Kesehatan</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body row g-3">
                        @include('facilities._form', ['facility' => null])
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
