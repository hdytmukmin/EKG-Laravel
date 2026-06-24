@extends('layouts.app')

@section('title', 'Rekaman EKG')
@section('page_title', 'Rekaman EKG')
@section('page_subtitle', 'Riwayat sesi pemeriksaan pasien berdasarkan puskesmas dan alat')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Total Sesi</div>
                    <div class="stat-value">{{ $summary['total_sessions'] }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-activity fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Sesi AF</div>
                    <div class="stat-value">{{ $summary['total_af'] }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-exclamation-diamond fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Sesi Non-AF</div>
                    <div class="stat-value">{{ $summary['total_non_af'] }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-check2-circle fs-4"></i></div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2 class="h5 fw-bold mb-0">Daftar Sesi Rekaman</h2>
            <span class="text-secondary small"><i class="bi bi-funnel me-1"></i>Search dan filter aktif</span>
        </div>
        <div class="panel-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-lg-5">
                    <label class="form-label small text-secondary">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Pasien, puskesmas, atau alat">
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small text-secondary">Status Sesi</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small text-secondary">Prediksi</label>
                    <select name="prediction" class="form-select">
                        <option value="">Semua</option>
                        <option value="AF" @selected(request('prediction') === 'AF')>AF</option>
                        <option value="NON_AF" @selected(request('prediction') === 'NON_AF')>Non-AF</option>
                        <option value="PENDING_MODEL" @selected(request('prediction') === 'PENDING_MODEL')>Pending</option>
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
                <div class="col-6 col-lg-1 d-grid">
                    <button class="btn btn-primary" type="submit" title="Terapkan filter"><i class="bi bi-funnel"></i></button>
                </div>
            </form>

            @include('recordings._sessions-table', ['sessions' => $sessions])
        </div>
    </div>
@endsection
