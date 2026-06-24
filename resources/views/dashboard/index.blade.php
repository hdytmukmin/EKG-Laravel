@extends('layouts.app')

@section('title', 'Dashboard EKG')
@section('page_title', 'Dashboard EKG')
@section('page_subtitle', 'Ringkasan pasien, rekaman, dan indikator denyut jantung')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Total Pasien</div>
                    <div class="stat-value">{{ $dashboard['total_patients'] }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-people fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Rekaman Lengkap</div>
                    <div class="stat-value">{{ $dashboard['completed_records'] }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-clipboard2-pulse fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Rata-rata BPM</div>
                    <div class="stat-value">{{ $dashboard['avg_bpm'] }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-heart-pulse fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Rekaman Terakhir</div>
                    <div class="fw-bold fs-5 text-break">{{ $dashboard['latest_record'] ?: '-' }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-clock-history fs-4"></i></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Tren BPM Pasien</h2>
                    <span class="badge text-bg-light">Data terakhir</span>
                </div>
                <div class="panel-body">
                    <div class="chart-box"><canvas id="bpmChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="panel h-100">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Status Sistem</h2>
                </div>
                <div class="panel-body">
                    <div class="d-flex justify-content-between border-bottom py-3">
                        <span class="text-secondary">Database</span>
                        <span class="badge {{ $dbError ? 'text-bg-warning' : 'text-bg-success' }}">{{ $dbError ? 'Check' : 'Connected' }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-3">
                        <span class="text-secondary">MQTT Broker</span>
                        <span class="badge text-bg-success">Active</span>
                    </div>
                    <div class="d-flex justify-content-between py-3">
                        <span class="text-secondary">Mode Aplikasi</span>
                        <span class="badge text-bg-primary">Laravel</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2 class="h5 fw-bold mb-0">Data Terbaru</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatientModal">
                <i class="bi bi-person-plus me-1"></i> Pasien
            </button>
        </div>
        <div class="panel-body">
            @include('partials.table-controls')
            @include('patients._table', ['records' => $records, 'showActions' => false])
        </div>
    </div>

    @include('patients._create-modal')
@endsection

@push('scripts')
<script>
    const chartLabels = @json($dashboard['chart_labels']);
    const chartData = @json($dashboard['chart_bpm']);
    const ctx = document.getElementById('bpmChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'BPM',
                    data: chartData,
                    borderColor: '#0a84c1',
                    backgroundColor: 'rgba(10, 132, 193, .12)',
                    fill: true,
                    tension: .32,
                    pointRadius: 4
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, grid: { color: '#e5eef3' } }, x: { grid: { display: false } } }
            }
        });
    }
</script>
@endpush
