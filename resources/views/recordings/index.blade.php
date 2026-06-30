@extends('layouts.app')

@section('title', 'Rekaman EKG')
@section('page_title', 'Rekaman EKG')
@section('page_subtitle', 'Sesi pemeriksaan')

@section('content')
    <section class="page-hero">
        <div class="hero-kicker"><i class="bi bi-clipboard2-pulse"></i> Rekaman EKG</div>
        <h2>Sesi pemeriksaan EKG</h2>
        <div class="hero-actions">
            <span class="hero-chip"><i class="bi bi-funnel"></i> Filter</span>
            <span class="hero-chip"><i class="bi bi-graph-up"></i> Grafik</span>
            <span class="hero-chip"><i class="bi bi-shield-check"></i> Akses role</span>
        </div>
    </section>

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
            <div class="stat-card stat-danger">
                <div>
                    <div class="stat-label">Sesi AF</div>
                    <div class="stat-value">{{ $summary['total_af'] }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-exclamation-diamond fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card stat-success">
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
            <span class="text-secondary small"><i class="bi bi-funnel me-1"></i>Filter</span>
        </div>
        <div class="panel-body">
            <form method="GET" class="table-toolbar row g-2 align-items-end">
                <div class="col-12 col-xl-4">
                    <label class="form-label small text-secondary">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Pasien, puskesmas, atau alat">
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label small text-secondary">Puskesmas</label>
                    <select name="puskesmas_id" class="form-select" @disabled(! auth()->user()->isSuperAdmin())>
                        <option value="">Semua</option>
                        @foreach ($puskesmasOptions as $puskesmas)
                            <option value="{{ $puskesmas->id }}" @selected((int) request('puskesmas_id') === $puskesmas->id)>{{ $puskesmas->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label small text-secondary">Alat</label>
                    <select name="device_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($deviceOptions as $device)
                            <option value="{{ $device->id }}" @selected((int) request('device_id') === $device->id)>{{ $device->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label class="form-label small text-secondary">Pasien</label>
                    <select name="patient_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($patientOptions as $patient)
                            <option value="{{ $patient->id }}" @selected((int) request('patient_id') === $patient->id)>{{ $patient->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small text-secondary">Status Sesi</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small text-secondary">Prediksi</label>
                    <select name="prediction" class="form-select">
                        <option value="">Semua</option>
                        <option value="AF" @selected(request('prediction') === 'AF')>AF</option>
                        <option value="PERSISTENT_AF" @selected(request('prediction') === 'PERSISTENT_AF')>Persistent AF</option>
                        <option value="PAROXYSMAL_AF" @selected(request('prediction') === 'PAROXYSMAL_AF')>Paroxysmal AF</option>
                        <option value="NON_AF" @selected(request('prediction') === 'NON_AF')>Non-AF</option>
                        <option value="PENDING_MODEL" @selected(request('prediction') === 'PENDING_MODEL')>Pending</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small text-secondary">Dari Tanggal</label>
                    <input type="date" name="recorded_from" class="form-control" value="{{ request('recorded_from') }}">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small text-secondary">Sampai Tanggal</label>
                    <input type="date" name="recorded_to" class="form-control" value="{{ request('recorded_to') }}">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small text-secondary">Tampil</label>
                    <select name="per_page" class="form-select">
                        @foreach ([5, 10, 25, 50] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 10) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2 d-grid">
                    <button class="btn btn-primary" type="submit" title="Terapkan filter"><i class="bi bi-funnel"></i></button>
                </div>
                <div class="col-12 col-xl-2 d-grid">
                    <a class="btn btn-outline-secondary" href="{{ route('recordings.index') }}" title="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>

            @include('recordings._sessions-table', ['sessions' => $sessions])
        </div>
    </div>
@endsection
