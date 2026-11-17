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
    @php($totalSamples = $recording->rawSignal?->total_samples ?: count($rawSignal))
    @php($minimumSamples = max((int) config('ekg.minimum_samples', 5000), 2))
    @php($signalInsufficient = $totalSamples < $minimumSamples)
    @php($duration = count($rawSignal) / max($sampleRate, 1))
    @php($ekgWidth = min(max((int) ceil($duration * 220), 1100), 30000))
    @php($predictionText = $signalInsufficient ? 'Belum tersedia' : match ($prediction?->label) {
        'NON_AF' => 'Non-AF',
        'PENDING_MODEL' => 'Sedang diproses',
        default => $prediction?->label ?? '-',
    })

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div>
                    <div class="stat-label">BPM</div>
                    <div class="stat-value">{{ $feature?->bpm ? number_format($feature->bpm, 1) : 0 }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-heart-pulse fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div>
                    <div class="stat-label">RR Interval</div>
                    <div class="stat-value">{{ $feature?->rr ? number_format($feature->rr, 2) : 0 }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-soundwave fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Prediksi</div>
                    <div class="fw-bold fs-5" id="predictionLabel">{{ $predictionText }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-clipboard2-pulse fs-4"></i></div>
            </div>
        </div>
    </div>

    @if ($signalInsufficient)
        <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <strong>Sinyal EKG belum mencukupi.</strong>
                Tersedia {{ number_format($totalSamples, 0, ',', '.') }} dari minimal {{ number_format($minimumSamples, 0, ',', '.') }} sampel. Data tetap disimpan, tetapi tidak divisualisasikan dan belum digunakan untuk prediksi AF/Non-AF.
            </div>
        </div>
    @endif

    <div class="row g-4">
        @if (! $signalInsufficient)
        <div class="col-12 col-xl-8">
            <div class="panel mb-4">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Grafik BPM</h2>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge text-bg-light">{{ max(count($heartRate), max(count($rPeaks) - 1, 0)) }} titik</span>
                        <div class="btn-group" aria-label="Kontrol zoom grafik BPM">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-session-chart="bpm" data-zoom-action="in" title="Perbesar grafik BPM"><i class="bi bi-zoom-in"></i></button>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-session-chart="bpm" data-zoom-action="out" title="Perkecil grafik BPM"><i class="bi bi-zoom-out"></i></button>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-session-chart="bpm" data-zoom-action="reset" title="Reset zoom grafik BPM"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </div>
                    </div>
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
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge text-bg-light">{{ count($rawSignal) }} sample</span>
                        <div class="btn-group" aria-label="Kontrol zoom grafik EKG">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-session-chart="ekg" data-zoom-action="in" title="Perbesar grafik EKG"><i class="bi bi-zoom-in"></i></button>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-session-chart="ekg" data-zoom-action="out" title="Perkecil grafik EKG"><i class="bi bi-zoom-out"></i></button>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-session-chart="ekg" data-zoom-action="reset" title="Reset zoom grafik EKG"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </div>
                    </div>
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
        @endif
        <div class="col-12 {{ $signalInsufficient ? '' : 'col-xl-4' }}">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Informasi Pasien</h2>
                </div>
                <div class="panel-body">
                    <dl class="row mb-0">
                        <dt class="col-5 text-secondary">Nama</dt><dd class="col-7">{{ $recording->patient?->name ?? '-' }}</dd>
                        <dt class="col-5 text-secondary">Fasilitas Kesehatan</dt><dd class="col-7">{{ $recording->puskesmas?->name ?? '-' }}</dd>
                        <dt class="col-5 text-secondary">Alat</dt><dd class="col-7">{{ $recording->device?->name ?? '-' }}</dd>
                        <dt class="col-5 text-secondary">Tanggal</dt><dd class="col-7">{{ $recording->recorded_at?->format('Y-m-d H:i:s') ?? '-' }}</dd>
                    </dl>
                    <div id="predictionStatusMessage" class="alert alert-warning small mt-3 mb-0 {{ $prediction?->error_message && ! $signalInsufficient ? '' : 'd-none' }}">
                        <i class="bi bi-hourglass-split me-1"></i><span id="predictionStatusText">{{ $prediction?->error_message }}</span>
                    </div>
                    <a class="btn btn-outline-primary w-100 mt-3" href="{{ route('recordings.index') }}"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@if (! $signalInsufficient)
@push('scripts')
<script>
    const storedHeartRate = @json(array_values($heartRate));
    const rawSignal = @json(array_values($rawSignal));
    const filteredSignal = @json(array_values($filteredSignal));
    const rPeaks = @json(array_values($rPeaks));
    const sampleRate = @json($sampleRate);
    const featureBpm = @json($feature?->bpm);
    const featureRr = @json($feature?->rr);
    const initialPredictionLabel = @json($prediction?->label);
    const predictionStatusUrl = @json(route('recordings.prediction-status', $recording));

    function sessionZoomOptions(minRange = 0.5) {
        return {
            limits: { x: { min: 'original', max: 'original', minRange } },
            pan: { enabled: true, mode: 'x', threshold: 8 },
            zoom: {
                wheel: { enabled: true, modifierKey: 'ctrl', speed: 0.08 },
                pinch: { enabled: true },
                mode: 'x'
            }
        };
    }

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

    function lineChart(id, label, points, color, fill = false, yTitle = '', zoomEnabled = false) {
        const ctx = document.getElementById(id);
        if (!ctx) return null;
        return new Chart(ctx, {
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
                plugins: {
                    legend: { display: false },
                    zoom: zoomEnabled ? sessionZoomOptions(0.5) : undefined
                },
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
        if (!ctx) return null;

        const filtered = filteredValues.length ? filteredValues : rawValues;
        const rPeakPoints = rPeakIndices
            .filter(index => filtered[index] !== undefined)
            .map(index => ({ x: index / sampleRate, y: filtered[index] + 0.035 }));

        return new Chart(ctx, {
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
                    decimation: { enabled: true, algorithm: 'lttb', samples: 1200, threshold: 2000 },
                    zoom: sessionZoomOptions(0.25),
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

    const sessionBpmChart = lineChart('bpmChart', 'BPM', bpmPoints, 'rgba(10, 132, 193, 1)', true, 'BPM', true);
    lineChart('rrChart', 'RR Interval', rrPoints, 'rgba(25, 135, 84, 1)', false, 'RR (s)');
    const sessionEkgChart = createEcgViewerChart('rawChart', rawSignal, filteredSignal, rPeaks, sampleRate);

    document.querySelectorAll('[data-session-chart][data-zoom-action]').forEach(button => {
        button.addEventListener('click', () => {
            const chart = button.dataset.sessionChart === 'bpm' ? sessionBpmChart : sessionEkgChart;
            if (!chart) return;

            if (button.dataset.zoomAction === 'in') chart.zoom({ x: 1.2 });
            if (button.dataset.zoomAction === 'out') chart.zoom({ x: 0.8 });
            if (button.dataset.zoomAction === 'reset') chart.resetZoom();
        });
    });

    if (initialPredictionLabel === 'PENDING_MODEL') {
        const predictionLabel = document.getElementById('predictionLabel');
        const statusMessage = document.getElementById('predictionStatusMessage');
        const statusText = document.getElementById('predictionStatusText');
        let attempts = 0;
        const timer = window.setInterval(async () => {
            attempts++;
            try {
                const response = await fetch(predictionStatusUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const data = await response.json();
                predictionLabel.textContent = data.display_label;
                statusText.textContent = data.error_message || 'Prediksi AF/Non-AF sedang diproses.';
                statusMessage.classList.toggle('d-none', data.completed);
                if (data.completed || attempts >= 90) window.clearInterval(timer);
            } catch (error) {
                if (attempts >= 90) window.clearInterval(timer);
            }
        }, 2000);
    }
</script>
@endpush
@endif
