@extends('layouts.app')

@section('title', 'Detail Pasien')
@section('page_title', $patient->name)
@section('page_subtitle', 'Riwayat pemeriksaan dan tren BPM pasien')

@section('content')
    @php
        $sessionChartData = $sessions->map(function ($session) {
            return [
                'label' => $session->recorded_at?->format('Y-m-d H:i') ?? '#'.$session->id,
                'bpm' => $session->feature?->bpm,
                'raw' => $session->rawSignal?->voltage_values ?? [],
            ];
        })->values();
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
                <div style="width: {{ max(($sessionChartData->firstWhere('raw') ? count($sessionChartData->firstWhere('raw')['raw']) : 300) * 4, 1200) }}px; height: 320px">
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
    const raw = sessions.find(s => s.raw.length)?.raw || [];
    const ekgCtx = document.getElementById('patientEkgChart');
    if (ekgCtx) {
        new Chart(ekgCtx, { type: 'line', data: { labels: raw.map((_, i) => i + 1), datasets: [{ data: raw, borderColor: '#d63384', pointRadius: 0, borderWidth: 1.2 }] }, options: { maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } }, scales: { x: { display: false } } } });
    }
</script>
@endpush
