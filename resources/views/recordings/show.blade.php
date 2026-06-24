@extends('layouts.app')

@section('title', 'Detail Sesi Rekaman')
@section('page_title', 'Detail Sesi Rekaman')
@section('page_subtitle', $recording->patient?->name ?? 'Pasien tidak ditemukan')

@section('content')
    @php($feature = $recording->feature)
    @php($prediction = $recording->prediction)
    @php($heartRate = $feature?->heart_rate ?? [])
    @php($rawSignal = $recording->rawSignal?->voltage_values ?? [])

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">BPM</div>
                    <div class="stat-value">{{ $feature?->bpm ? number_format($feature->bpm, 1) : 0 }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-heart-pulse fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">RR Interval</div>
                    <div class="stat-value">{{ $feature?->rr ? number_format($feature->rr, 2) : 0 }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-soundwave fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Interval PT</div>
                    <div class="stat-value">{{ $feature?->interval_pt ? number_format($feature->interval_pt, 2) : 0 }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-graph-up fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Prediksi</div>
                    <div class="fw-bold fs-5">{{ $prediction?->label === 'PENDING_MODEL' ? 'Belum tersedia' : ($prediction?->label ?? '-') }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-clipboard2-pulse fs-4"></i></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="panel mb-4">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Grafik BPM</h2>
                    <span class="badge text-bg-light">{{ count($heartRate) }} titik</span>
                </div>
                <div class="panel-body">
                    <div class="chart-box"><canvas id="bpmChart"></canvas></div>
                </div>
            </div>

            <div class="panel mb-4">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Grafik RR Interval</h2>
                </div>
                <div class="panel-body">
                    <div class="chart-box"><canvas id="rrChart"></canvas></div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Grafik Sinyal EKG</h2>
                    <span class="badge text-bg-light">{{ count($rawSignal) }} sample</span>
                </div>
                <div class="panel-body">
                    <div class="ekg-scroll">
                        <div style="width: {{ max(count($rawSignal) * 4, 900) }}px; height: 360px">
                            <canvas id="rawChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Informasi Pasien</h2>
                </div>
                <div class="panel-body">
                    <dl class="row mb-0">
                        <dt class="col-5 text-secondary">Nama</dt><dd class="col-7">{{ $recording->patient?->name ?? '-' }}</dd>
                        <dt class="col-5 text-secondary">Puskesmas</dt><dd class="col-7">{{ $recording->puskesmas?->name ?? '-' }}</dd>
                        <dt class="col-5 text-secondary">Alat</dt><dd class="col-7">{{ $recording->device?->name ?? '-' }}</dd>
                        <dt class="col-5 text-secondary">Tanggal</dt><dd class="col-7">{{ $recording->recorded_at?->format('Y-m-d H:i:s') ?? '-' }}</dd>
                        <dt class="col-5 text-secondary">SDNN</dt><dd class="col-7">{{ $feature?->sdnn ?? '-' }}</dd>
                        <dt class="col-5 text-secondary">SNS</dt><dd class="col-7">{{ $feature?->sns ?? '-' }}</dd>
                    </dl>
                    @if ($prediction?->error_message)
                        <div class="alert alert-warning small mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>{{ $prediction->error_message }}
                        </div>
                    @endif
                    <a class="btn btn-outline-primary w-100 mt-3" href="{{ route('recordings.index') }}"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const heartRate = @json(array_values($heartRate));
    const rawSignal = @json(array_values($rawSignal));
    const rrValue = @json($feature?->rr);
    const rrSeries = heartRate.length ? heartRate.map(() => rrValue) : [rrValue];

    function lineChart(id, label, values, color, fill = false) {
        const ctx = document.getElementById(id);
        if (!ctx) return;
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: values.map((_, index) => index + 1),
                datasets: [{
                    label,
                    data: values,
                    borderColor: color,
                    backgroundColor: fill ? color.replace('1)', '.12)') : 'transparent',
                    fill,
                    tension: .22,
                    pointRadius: values.length > 250 ? 0 : 2,
                    borderWidth: 1.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: values.length < 1200,
                plugins: { legend: { display: false } },
                scales: { x: { grid: { display: false } }, y: { grid: { color: '#e5eef3' } } }
            }
        });
    }

    lineChart('bpmChart', 'BPM', heartRate, 'rgba(10, 132, 193, 1)', true);
    lineChart('rrChart', 'RR Interval', rrSeries, 'rgba(25, 135, 84, 1)', false);
    lineChart('rawChart', 'Voltage', rawSignal, 'rgba(214, 51, 132, 1)', false);
</script>
@endpush
