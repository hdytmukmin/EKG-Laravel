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
                    <div class="stat-label">Total Pasien AF</div>
                    <div class="stat-value">{{ $dashboard['total_af'] }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-exclamation-diamond fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Total Pasien Non-AF</div>
                    <div class="stat-value">{{ $dashboard['total_non_af'] }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-check2-circle fs-4"></i></div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Sesi Terakhir</div>
                    <div class="fw-bold fs-5 text-break">{{ optional($dashboard['latest_session'])->recorded_at?->format('Y-m-d H:i') ?? '-' }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-clock-history fs-4"></i></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Grafik EKG dan BPM</h2>
                    <span class="badge text-bg-light">Sesi terakhir</span>
                </div>
                <div class="panel-body">
                    @php($latest = $dashboard['latest_session'])
                    @if ($latest)
                        <div class="chart-box mb-4"><canvas id="ekgChart"></canvas></div>
                        <div class="chart-box"><canvas id="bpmChart"></canvas></div>
                    @else
                        <div class="empty-state">Belum ada sesi rekaman.</div>
                    @endif
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
            <h2 class="h5 fw-bold mb-0">Sesi Rekaman Terbaru</h2>
            <a class="btn btn-primary" href="{{ route('patients.index') }}"><i class="bi bi-people me-1"></i>Pasien</a>
        </div>
        <div class="panel-body">
            @include('recordings._sessions-table', ['sessions' => $sessions])
        </div>
    </div>

    @if ($dashboard['is_super_admin'])
        <div class="panel mt-4">
            <div class="panel-header"><h2 class="h5 fw-bold mb-0">Breakdown Puskesmas</h2></div>
            <div class="panel-body table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Puskesmas</th><th>Pasien</th><th>Sesi</th></tr></thead>
                    <tbody>
                        @foreach ($dashboard['breakdown'] as $site)
                            <tr><td>{{ $site->name }}</td><td>{{ $site->patients_count }}</td><td>{{ $site->recording_sessions_count }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    const latestHeartRate = @json(optional(optional($dashboard['latest_session'])->feature)->heart_rate ?? []);
    const latestRaw = @json(optional(optional($dashboard['latest_session'])->rawSignal)->voltage_values ?? []);

    const bpmCtx = document.getElementById('bpmChart');
    if (bpmCtx) {
        new Chart(bpmCtx, {
            type: 'line',
            data: {
                labels: latestHeartRate.map((_, index) => index + 1),
                datasets: [{
                    label: 'BPM',
                    data: latestHeartRate,
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

    const ekgCtx = document.getElementById('ekgChart');
    if (ekgCtx) {
        new Chart(ekgCtx, {
            type: 'line',
            data: {
                labels: latestRaw.map((_, index) => index + 1),
                datasets: [{ label: 'Voltage', data: latestRaw, borderColor: '#d63384', pointRadius: 0, borderWidth: 1.5 }]
            },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { grid: { color: '#e5eef3' } } } }
        });
    }
</script>
@endpush
