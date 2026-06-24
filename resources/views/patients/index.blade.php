@extends('layouts.app')

@section('title', 'Pasien EKG')
@section('page_title', 'Manajemen Pasien')
@section('page_subtitle', 'Tambah, edit, cari, dan siapkan pasien untuk data dari alat IoT')

@section('content')
    <div class="panel">
        <div class="panel-header">
            <h2 class="h5 fw-bold mb-0">Daftar Pasien</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatientModal">
                <i class="bi bi-person-plus me-1"></i> Pasien
            </button>
        </div>
        <div class="panel-body">
            @include('partials.table-controls')
            @include('patients._table', ['records' => $records, 'showActions' => true])
        </div>
    </div>
    @include('patients._create-modal')
@endsection
