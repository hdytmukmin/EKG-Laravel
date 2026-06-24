@extends('layouts.app')

@section('title', 'Dashboard EKG')
@section('page_title', 'Dashboard EKG')
@section('page_subtitle', $dashboard['is_super_admin'] ? 'Monitoring global seluruh puskesmas' : 'Monitoring internal puskesmas')

@section('content')
    @php
        $chartSession = $dashboard['chart_session'];
        $latest = $dashboard['latest_session'];
        $latestHeartRate = $chartSession?->feature?->heart_rate ?? [];
        $latestRaw = $chartSession?->rawSignal?->voltage_values ?? [];
    @endphp

    <form method="GET" class="panel mb-4">
        <div class="panel-body row g-3 align-items-end">
            <div class="col-12 col-lg-3">
                <label class="form-label small text-secondary">Puskesmas</label>
                <select name="puskesmas_id" class="form-select" @disabled(! auth()->user()->isSuperAdmin())>
                    <option value="">Semua Puskesmas</option>
                    @foreach ($puskesmasOptions as $puskesmas)
                        <option value="{{ $puskesmas->id }}" @selected((int) request('puskesmas_id') === $puskesmas->id)>{{ $puskesmas->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label small text-secondary">Alat</label>
                <select name="device_id" class="form-select">
                    <option value="">Semua Alat</option>
                    @foreach ($devices as $device)
                        <option value="{{ $device->id }}" @selected((int) request('device_id') === $device->id)>{{ $device->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label small text-secondary">Sesi Grafik</label>
                <select name="session_id" class="form-select">
                    <option value="">Sesi terakhir</option>
                    @foreach ($dashboard['chart_sessions'] as $session)
                        <option value="{{ $session->id }}" @selected((int) request('session_id') === $session->id)>
                            #{{ $session->id }} - {{ $session->patient?->name ?? '-' }} - {{ $session->recorded_at?->format('Y-m-d H:i') ?? '-' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid">
                <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Terapkan</button>
            </div>
        </div>
    </form>

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
                    <div class="fw-bold fs-5 text-break">{{ $latest?->recorded_at?->format('Y-m-d H:i') ?? '-' }}</div>
                </div>
                <div class="stat-icon"><i class="bi bi-clock-history fs-4"></i></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="h5 fw-bold mb-0">Grafik EKG dan BPM</h2>
                        <div class="small text-secondary">{{ $chartSession?->patient?->name ?? 'Belum ada sesi' }} {{ $chartSession?->recorded_at ? '- '.$chartSession->recorded_at->format('Y-m-d H:i') : '' }}</div>
                    </div>
                    <span class="badge text-bg-light">{{ count($latestRaw) }} sample</span>
                </div>
                <div class="panel-body">
                    @if ($chartSession)
                        <div class="ekg-scroll mb-4">
                            <div style="width: {{ max(count($latestRaw) * 4, 900) }}px; height: 310px">
                                <canvas id="ekgChart"></canvas>
                            </div>
                        </div>
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
                        <span class="badge text-bg-success">Configured</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-3">
                        <span class="text-secondary">Alat Terdaftar</span>
                        <span class="badge text-bg-primary">{{ $devices->count() }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-3">
                        <span class="text-secondary">Mode Aplikasi</span>
                        <span class="badge text-bg-primary">Laravel</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($dashboard['is_super_admin'])
        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-5">
                <div class="panel h-100">
                    <div class="panel-header">
                        <h2 class="h5 fw-bold mb-0">Global AF vs Non-AF</h2>
                    </div>
                    <div class="panel-body">
                        <div class="chart-box"><canvas id="globalAfChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-7">
                <div class="panel h-100">
                    <div class="panel-header"><h2 class="h5 fw-bold mb-0">Breakdown Puskesmas</h2></div>
                    <div class="panel-body table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Puskesmas</th><th>Pasien</th><th>AF</th><th>Non-AF</th><th>Sesi</th><th>Rekaman Terakhir</th></tr></thead>
                            <tbody>
                                @foreach ($dashboard['breakdown'] as $site)
                                    <tr>
                                        <td>{{ $site->name }}</td>
                                        <td>{{ $site->patients_count }}</td>
                                        <td><span class="badge text-bg-danger">{{ $site->af_patients }}</span></td>
                                        <td><span class="badge text-bg-success">{{ $site->non_af_patients }}</span></td>
                                        <td>{{ $site->recording_sessions_count }}</td>
                                        <td>{{ $site->latest_recorded_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="panel">
        <div class="panel-header">
            <h2 class="h5 fw-bold mb-0">Sesi Rekaman Terbaru</h2>
            <a class="btn btn-primary" href="{{ route('patients.index') }}"><i class="bi bi-people me-1"></i>Pasien</a>
        </div>
        <div class="panel-body">
            @include('recordings._sessions-table', ['sessions' => $sessions])
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const latestHeartRate = @json(array_values($latestHeartRate));
    const latestRaw = @json(array_values($latestRaw));
    const comparisonLabels = @json($dashboard['comparison_labels']);
    const comparisonAf = @json($dashboard['comparison_af']);
    const comparisonNonAf = @json($dashboard['comparison_non_af']);

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
                    pointRadius: latestHeartRate.length > 250 ? 0 : 3
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
                datasets: [{ label: 'Voltage', data: latestRaw, borderColor: '#d63384', pointRadius: 0, borderWidth: 1.4 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: latestRaw.length < 1200,
                plugins: { legend: { display: false } },
                scales: { x: { display: false }, y: { grid: { color: '#e5eef3' } } }
            }
        });
    }

    const globalCtx = document.getElementById('globalAfChart');
    if (globalCtx) {
        new Chart(globalCtx, {
            type: 'bar',
            data: {
                labels: comparisonLabels,
                datasets: [
                    { label: 'AF', data: comparisonAf, backgroundColor: '#dc3545' },
                    { label: 'Non-AF', data: comparisonNonAf, backgroundColor: '#198754' }
                ]
            },
            options: {
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, precision: 0 } }
            }
        });
    }
</script>
@endpush
