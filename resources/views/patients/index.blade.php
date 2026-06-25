@extends('layouts.app')

@section('title', 'Pasien EKG')
@section('page_title', 'Manajemen Pasien')
@section('page_subtitle', 'Data pasien')

@section('content')
    <section class="page-hero">
        <div class="hero-kicker"><i class="bi bi-person-vcard"></i> Pasien</div>
        <h2>Data pasien dan riwayat pemeriksaan</h2>
        <div class="hero-actions">
            <span class="hero-chip"><i class="bi bi-search"></i> Pencarian</span>
            <span class="hero-chip"><i class="bi bi-hospital"></i> Puskesmas</span>
            <span class="hero-chip"><i class="bi bi-clock-history"></i> Riwayat sesi</span>
        </div>
    </section>

    <div class="panel">
        <div class="panel-header">
            <h2 class="h5 fw-bold mb-0">Daftar Pasien</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatientModal">
                <i class="bi bi-person-plus me-1"></i> Pasien
            </button>
        </div>
        <div class="panel-body">
            <form method="GET" class="table-toolbar row g-2 align-items-end">
                <div class="col-12 col-lg-8">
                    <label class="form-label small text-secondary">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nama, alamat, subject id">
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small text-secondary">Tampil</label>
                    <select name="per_page" class="form-select">
                        @foreach ([5, 10, 25, 50] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 10) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2 d-grid"><button class="btn btn-primary"><i class="bi bi-search"></i></button></div>
            </form>
            @include('patients._table', ['patients' => $patients])
        </div>
    </div>
    @include('patients._create-modal')
@endsection
