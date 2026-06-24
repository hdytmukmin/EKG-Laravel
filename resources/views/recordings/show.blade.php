@extends('layouts.app')

@section('title', 'Detail Rekaman EKG')
@section('page_title', 'Detail Rekaman EKG')
@section('page_subtitle', $recording->nama ?: 'Pasien tanpa nama')

@section('content')
    @php($status = $recording->status())
    @php($series = $recording->heartRateSeries())

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">BPM</div>
                    <div class="stat-value">{{ $recording->bpm ?: 0 }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-heart-pulse fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">TSPT</div>
                    <div class="stat-value">{{ $recording->tspt ?: 0 }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-graph-up fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">IRR</div>
                    <div class="stat-value">{{ $recording->irr ?: 0 }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-soundwave fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Status</div>
                    <span class="badge {{ $status['class'] }}"><i class="bi {{ $status['icon'] }} me-1"></i>{{ $status['label'] }}</span>
                </div>
                <div class="stat-icon"><i class="bi bi-clipboard2-check fs-4"></i></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Grafik Heart Rate</h2>
                    <span class="badge text-bg-light">{{ count($series) }} titik</span>
                </div>
                <div class="panel-body">
                    <div class="chart-box"><canvas id="hrChart"></canvas></div>
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
                        <dt class="col-5 text-secondary">Nama</dt><dd class="col-7">{{ $recording->nama ?: '-' }}</dd>
                        <dt class="col-5 text-secondary">Umur</dt><dd class="col-7">{{ $recording->umur ?: '-' }}</dd>
                        <dt class="col-5 text-secondary">Gender</dt><dd class="col-7">{{ $recording->jk ?: '-' }}</dd>
                        <dt class="col-5 text-secondary">Tanggal</dt><dd class="col-7">{{ $recording->tglrekam ?: '-' }}</dd>
                        <dt class="col-5 text-secondary">Prediksi</dt><dd class="col-7">{{ $prediction }}</dd>
                    </dl>
                    @if ($predictionError)
                        <div class="alert alert-warning small mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>{{ $predictionError }}
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
    const hrSeries = @json($series);
    const hrCtx = document.getElementById('hrChart');
    if (hrCtx) {
        new Chart(hrCtx, {
            type: 'line',
            data: {
                labels: hrSeries.map((_, index) => index + 1),
                datasets: [{
                    label: 'Heart Rate',
                    data: hrSeries,
                    borderColor: '#d63384',
                    backgroundColor: 'rgba(214, 51, 132, .1)',
                    fill: true,
                    tension: .24,
                    pointRadius: 0
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { grid: { color: '#e5eef3' } }, x: { grid: { display: false } } }
            }
        });
    }
</script>
@endpush
