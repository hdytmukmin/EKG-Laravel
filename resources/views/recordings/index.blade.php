@extends('layouts.app')

@section('title', 'Rekaman EKG')
@section('page_title', 'Rekaman EKG')
@section('page_subtitle', 'Data EKG dari database legacy iot.recordekg')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Total Data</div>
                    <div class="stat-value">{{ $dashboard['total_patients'] }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-database fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Rekaman Lengkap</div>
                    <div class="stat-value">{{ $dashboard['completed_records'] }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-check2-circle fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Rata-rata BPM</div>
                    <div class="stat-value">{{ $dashboard['avg_bpm'] }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-heart-pulse fs-4"></i></div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2 class="h5 fw-bold mb-0">Daftar Rekaman</h2>
            <span class="text-secondary small"><i class="bi bi-funnel me-1"></i>Search dan filter aktif</span>
        </div>
        <div class="panel-body">
            @include('partials.table-controls')
            @include('patients._table', ['records' => $records, 'showActions' => false])
        </div>
    </div>
@endsection
