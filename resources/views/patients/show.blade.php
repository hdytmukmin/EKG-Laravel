@extends('layouts.app')

@section('title', 'Detail Pasien')
@section('page_title', $patient->name)
@section('page_subtitle', 'Riwayat pasien')

@section('content')
    @php
        $sessionChartData = $sessions->map(function ($session) {
            return [
                'label' => $session->recorded_at?->format('Y-m-d H:i') ?? '#'.$session->id,
                'bpm' => $session->feature?->bpm,
                'raw' => $session->rawSignal?->voltage_values ?? [],
                'filtered' => $session->rawSignal?->filtered_values ?? [],
                'r_peaks' => $session->rawSignal?->r_peak_indices ?? [],
                'sample_rate' => $session->rawSignal?->sample_rate ?? 360,
            ];
        })->values();
        $firstRawSession = $sessionChartData->first(fn ($session) => count($session['raw']) > 0);
        $firstDuration = $firstRawSession ? count($firstRawSession['raw']) / max($firstRawSession['sample_rate'], 1) : 5;
        $firstEkgWidth = min(max((int) ceil($firstDuration * 220), 1200), 30000);
    @endphp

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="panel">
                <div class="panel-header"><h2 class="h5 fw-bold mb-0">Informasi Pasien</h2></div>
                <div class="panel-body">
                    <dl class="row mb-0">
                        <dt class="col-5 text-secondary">Puskesmas</dt><dd class="col-7">{{ $patient->puskesmas?->name }}</dd>
                        <dt class="col-5 text-secondary">Umur</dt><dd class="col-7">{{ $patient->age ?? '-' }}</dd>
                        <dt class="col-5 text-secondary">Gender</dt><dd class="col-7">{{ $patient->gender ?? '-' }}</dd>
                        <dt class="col-5 text-secondary">Alamat</dt><dd class="col-7">{{ $patient->address ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-8">
            <div class="panel">
                <div class="panel-header"><h2 class="h5 fw-bold mb-0">Tren BPM</h2></div>
                <div class="panel-body"><div class="chart-box"><canvas id="patientBpmChart"></canvas></div></div>
            </div>
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-header"><h2 class="h5 fw-bold mb-0">Riwayat Sesi</h2></div>
        <div class="panel-body">
            @include('recordings._sessions-table', ['sessions' => $sessions])
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-header"><h2 class="h5 fw-bold mb-0">Visualisasi Sinyal EKG</h2></div>
        <div class="panel-body">
            <div class="ekg-scroll">
                <div style="width: {{ $firstEkgWidth }}px; height: 320px">
                    <canvas id="patientEkgChart"></canvas>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const sessions = @json($sessionChartData);
    const bpmCtx = document.getElementById('patientBpmChart');
    if (bpmCtx) {
        new Chart(bpmCtx, { type: 'line', data: { labels: sessions.map(s => s.label), datasets: [{ data: sessions.map(s => s.bpm), borderColor: '#0a84c1', tension: .25 }] }, options: { maintainAspectRatio: false, plugins: { legend: { display: false } } } });
    }
    const signalSession = sessions.find(s => s.raw.length) || {};
    const raw = signalSession.raw || [];
    const filtered = signalSession.filtered || [];
    const rPeaks = signalSession.r_peaks || [];
    const sampleRate = signalSession.sample_rate || 360;
    const ekgCtx = document.getElementById('patientEkgChart');
    if (ekgCtx) {
        const processed = filtered.length ? filtered : raw;
        const rPeakPoints = rPeaks
            .filter(index => processed[index] !== undefined)
            .map(index => ({ x: index / sampleRate, y: processed[index] + 0.035 }));
        const toPoints = values => values.map((value, index) => ({ x: index / sampleRate, y: value }));

        new Chart(ekgCtx, {
            type: 'line',
            data: {
                datasets: [
                    { label: 'Raw', data: toPoints(raw), borderColor: 'rgba(150, 158, 166, .42)', pointRadius: 0, borderWidth: 1, tension: .12 },
                    { label: 'Filtered', data: toPoints(processed), borderColor: '#1f77b4', pointRadius: 0, borderWidth: 1.45, tension: .1 },
                    { label: 'R-peaks', type: 'scatter', data: rPeakPoints, borderColor: '#e2364f', backgroundColor: '#e2364f', pointStyle: 'triangle', pointRotation: 180, pointRadius: 5 }
                ]
            },
            options: {
                maintainAspectRatio: false,
                animation: false,
                normalized: true,
                parsing: false,
                plugins: { legend: { position: 'top', align: 'start' } },
                scales: {
                    x: { type: 'linear', title: { display: true, text: 'Waktu (s)' }, ticks: { callback: value => `${Number(value).toFixed(1)}s`, maxRotation: 0 } },
                    y: { title: { display: true, text: 'Amplitude' }, grid: { color: 'rgba(16, 42, 53, .08)' } }
                }
            }
        });
    }
</script>
@endpush
