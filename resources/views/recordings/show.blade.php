@extends('layouts.app')

@section('title', 'Detail Sesi Rekaman')
@section('page_title', 'Detail Sesi Rekaman')
@section('page_subtitle', $recording->patient?->name ?? 'Pasien tidak ditemukan')

@section('content')
    @php($feature = $recording->feature)
    @php($prediction = $recording->prediction)
    @php($heartRate = $feature?->heart_rate ?? [])
    @php($rawSignal = $recording->rawSignal?->voltage_values ?? [])
    @php($filteredSignal = $recording->rawSignal?->filtered_values ?? [])
    @php($rPeaks = $recording->rawSignal?->r_peak_indices ?? [])
    @php($sampleRate = $recording->rawSignal?->sample_rate ?? 360)
    @php($duration = count($rawSignal) / max($sampleRate, 1))
    @php($ekgWidth = min(max((int) ceil($duration * 220), 1100), 30000))
    @php($predictionText = match ($prediction?->label) {
        'PERSISTENT_AF' => 'Persistent AF',
        'PAROXYSMAL_AF' => 'Paroxysmal AF',
        'NON_AF' => 'Non-AF',
        'PENDING_MODEL' => 'Belum tersedia',
        default => $prediction?->label ?? '-',
    })

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
                    <div class="fw-bold fs-5">{{ $predictionText }}</div>
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
                    <span class="badge text-bg-light">{{ max(count($heartRate), max(count($rPeaks) - 1, 0)) }} titik</span>
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
                        <div style="width: {{ $ekgWidth }}px; height: 360px">
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
    const storedHeartRate = @json(array_values($heartRate));
    const rawSignal = @json(array_values($rawSignal));
    const filteredSignal = @json(array_values($filteredSignal));
    const rPeaks = @json(array_values($rPeaks));
    const sampleRate = @json($sampleRate);
    const featureBpm = @json($feature?->bpm);
    const featureRr = @json($feature?->rr);

    function rrPointsFromPeaks(rPeakIndices, sampleRate) {
        const points = [];
        for (let index = 1; index < rPeakIndices.length; index++) {
            const rr = (rPeakIndices[index] - rPeakIndices[index - 1]) / sampleRate;
            if (Number.isFinite(rr) && rr > 0) {
                points.push({ x: rPeakIndices[index] / sampleRate, y: rr });
            }
        }
        return points;
    }

    function bpmPointsFromDatabase(storedHeartRate, rPeakIndices, sampleRate, fallbackBpm) {
        const rrPoints = rrPointsFromPeaks(rPeakIndices, sampleRate);
        if (rrPoints.length) {
            return rrPoints.map(point => ({ x: point.x, y: 60 / point.y }));
        }

        if (storedHeartRate.length) {
            return storedHeartRate
                .map((value, index) => ({ x: index + 1, y: Number(value) }))
                .filter(point => Number.isFinite(point.y));
        }

        return Number.isFinite(Number(fallbackBpm)) && Number(fallbackBpm) >= 0
            ? [{ x: 1, y: Number(fallbackBpm) }]
            : [];
    }

    function rrPointsFromDatabase(rPeakIndices, sampleRate, fallbackRr) {
        const rrPoints = rrPointsFromPeaks(rPeakIndices, sampleRate);
        if (rrPoints.length) {
            return rrPoints;
        }

        return Number.isFinite(Number(fallbackRr)) && Number(fallbackRr) >= 0
            ? [{ x: 1, y: Number(fallbackRr) }]
            : [];
    }

    function lineChart(id, label, points, color, fill = false, yTitle = '') {
        const ctx = document.getElementById(id);
        if (!ctx) return;
        new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label,
                    data: points,
                    borderColor: color,
                    backgroundColor: fill ? color.replace('1)', '.12)') : 'transparent',
                    fill,
                    tension: .22,
                    pointRadius: points.length > 250 ? 0 : 3,
                    borderWidth: 1.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: points.length < 1200,
                parsing: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        type: 'linear',
                        title: { display: true, text: points.some(point => point.x > 10) ? 'Waktu (s)' : 'Beat' },
                        grid: { display: false }
                    },
                    y: {
                        title: { display: Boolean(yTitle), text: yTitle },
                        grid: { color: '#e5eef3' }
                    }
                }
            }
        });
    }

    function secondsLabel(value) {
        return `${Number(value).toFixed(1)}s`;
    }

    function ecgPoints(values, sampleRate) {
        return values.map((value, index) => ({ x: index / sampleRate, y: value }));
    }

    function createEcgViewerChart(canvasId, rawValues, filteredValues, rPeakIndices, sampleRate) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        const filtered = filteredValues.length ? filteredValues : rawValues;
        const rPeakPoints = rPeakIndices
            .filter(index => filtered[index] !== undefined)
            .map(index => ({ x: index / sampleRate, y: filtered[index] + 0.035 }));

        new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [
                    {
                        label: 'Raw',
                        data: ecgPoints(rawValues, sampleRate),
                        borderColor: 'rgba(150, 158, 166, .42)',
                        backgroundColor: 'rgba(150, 158, 166, .18)',
                        pointRadius: 0,
                        borderWidth: 1,
                        tension: .12
                    },
                    {
                        label: 'Filtered',
                        data: ecgPoints(filtered, sampleRate),
                        borderColor: '#1f77b4',
                        backgroundColor: 'rgba(31, 119, 180, .1)',
                        pointRadius: 0,
                        borderWidth: 1.45,
                        tension: .1
                    },
                    {
                        label: 'R-peaks',
                        type: 'scatter',
                        data: rPeakPoints,
                        borderColor: '#e2364f',
                        backgroundColor: '#e2364f',
                        pointStyle: 'triangle',
                        pointRotation: 180,
                        pointRadius: 5,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                normalized: true,
                parsing: false,
                interaction: { mode: 'nearest', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'start' },
                    tooltip: {
                        callbacks: {
                            title: items => items.length ? `Waktu ${secondsLabel(items[0].parsed.x)}` : '',
                            label: item => `${item.dataset.label}: ${Number(item.parsed.y).toFixed(4)}`
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        title: { display: true, text: 'Waktu (s)' },
                        ticks: { callback: secondsLabel, maxRotation: 0 },
                        grid: { color: 'rgba(16, 42, 53, .08)' }
                    },
                    y: {
                        title: { display: true, text: 'Amplitude' },
                        grid: { color: 'rgba(16, 42, 53, .08)' }
                    }
                }
            }
        });
    }

    const bpmPoints = bpmPointsFromDatabase(storedHeartRate, rPeaks, sampleRate, featureBpm);
    const rrPoints = rrPointsFromDatabase(rPeaks, sampleRate, featureRr);

    lineChart('bpmChart', 'BPM', bpmPoints, 'rgba(10, 132, 193, 1)', true, 'BPM');
    lineChart('rrChart', 'RR Interval', rrPoints, 'rgba(25, 135, 84, 1)', false, 'RR (s)');
    createEcgViewerChart('rawChart', rawSignal, filteredSignal, rPeaks, sampleRate);
</script>
@endpush
