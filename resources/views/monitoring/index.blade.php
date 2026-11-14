@extends('layouts.app')

@section('title', 'Monitoring Service')
@section('page_title', 'Monitoring Service')
@section('page_subtitle', 'Status sistem')

@section('content')
    @php($onlineDevices = $devices->filter(fn ($device) => $device->effectiveStatus() === 'online')->count())
    @php($offlineDevices = $devices->filter(fn ($device) => $device->effectiveStatus() === 'offline')->count())
    @php($initialChartData = [
        'heart_rate' => $latest?->feature?->heart_rate ?? [],
        'raw_signal' => [],
        'filtered_signal' => [],
        'r_peaks' => [],
        'sample_rate' => 360,
    ])

    <section class="page-hero">
        <div class="hero-kicker"><i class="bi bi-broadcast"></i> Monitoring</div>
        <h2>Status sistem dan alat EKG</h2>
        <div class="hero-actions">
            <span class="hero-chip"><i class="bi bi-arrow-repeat"></i> Refresh 10 detik</span>
            <span class="hero-chip"><i class="bi bi-database-check"></i> Database</span>
            <span class="hero-chip"><i class="bi bi-cpu"></i> Alat EKG</span>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Status Sistem</h2>
                </div>
                <div class="panel-body">
                    <div class="status-list">
                    <div class="status-row">
                        <span>Database</span>
                        <span id="databaseStatus" class="badge {{ $dbError ? 'text-bg-warning' : 'text-bg-success' }}">{{ $dbError ? 'Check' : 'Connected' }}</span>
                    </div>
                    <div class="status-row">
                        <span>MQTT Broker</span>
                        <span class="badge text-bg-success">Configured</span>
                    </div>
                    <div class="status-row">
                        <span>Subscriber</span>
                        <span class="badge text-bg-primary">php artisan mqtt:listen</span>
                    </div>
                    </div>
                    <div class="small text-secondary mt-2">Last check: <span id="lastCheck">-</span></div>
                </div>
            </div>

            <div class="panel mt-4">
                <div class="panel-header">
                    <div>
                        <h2 class="h5 fw-bold mb-0">Klasifikasi Pasien</h2>
                        <div class="small text-secondary">Berdasarkan sesi rekaman terbaru setiap pasien</div>
                    </div>
                    <span class="badge text-bg-light"><span id="classificationTotal">{{ $classification['total'] }}</span> pasien</span>
                </div>
                <div class="panel-body">
                    <div class="row align-items-center g-4">
                        <div class="col-12 col-md-7">
                            <div class="chart-box"><canvas id="classificationChart"></canvas></div>
                        </div>
                        <div class="col-12 col-md-5">
                            <div class="status-list">
                                <div class="status-row">
                                    <span>AF</span>
                                    <span id="classificationAf" class="badge text-bg-danger">{{ $classification['af'] }}</span>
                                </div>
                                <div class="status-row">
                                    <span>Non-AF</span>
                                    <span id="classificationNonAf" class="badge text-bg-success">{{ $classification['non_af'] }}</span>
                                </div>
                                <div class="status-row">
                                    <span>Belum diklasifikasi</span>
                                    <span id="classificationPending" class="badge text-bg-secondary">{{ $classification['unclassified'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel mt-4">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Alat EKG</h2>
                    <div class="d-flex gap-2">
                        <span class="badge text-bg-success"><span id="onlineDeviceCount">{{ $onlineDevices }}</span> Online</span>
                        <span class="badge text-bg-danger"><span id="offlineDeviceCount">{{ $offlineDevices }}</span> Offline</span>
                    </div>
                </div>
                <div class="panel-body">
                    @forelse ($devices as $device)
                        <div class="status-row mb-2" data-device-id="{{ $device->id }}">
                            <div>
                                <div class="fw-bold">{{ $device->name }}</div>
                                <div class="small text-secondary">{{ $device->puskesmas?->name ?? '-' }}</div>
                                <div class="small text-secondary">Terakhir terhubung: <span data-device-last-seen>{{ $device->last_seen_at?->format('Y-m-d H:i:s') ?? '-' }}</span></div>
                            </div>
                            <span class="badge {{ $device->statusBadgeClass() }}" data-device-status>{{ $device->statusLabel() }}</span>
                        </div>
                    @empty
                        <div class="empty-state">Belum ada alat.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-8">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Data Terakhir</h2>
                    <span class="text-secondary small">Dari recording_sessions</span>
                </div>
                <div class="panel-body">
                    @if ($latest)
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <div class="stat-card">
                                    <div>
                                        <div class="stat-label">Pasien</div>
                                        <div id="latestName" class="fw-bold fs-5">{{ $latest->patient?->name ?? '-' }}</div>
                                    </div>
                                    <div class="stat-icon"><i class="bi bi-person-vcard fs-4"></i></div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="stat-card stat-warning">
                                    <div>
                                        <div class="stat-label">BPM</div>
                                        <div id="latestBpm" class="stat-value">{{ $latest->feature?->bpm ? number_format($latest->feature->bpm, 1) : 0 }}</div>
                                    </div>
                                    <div class="stat-icon"><i class="bi bi-heart-pulse fs-4"></i></div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="stat-card">
                                    <div>
                                        <div class="stat-label">Tanggal</div>
                                        <div id="latestTime" class="fw-bold text-break">{{ $latest->recorded_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                                    </div>
                                    <div class="stat-icon"><i class="bi bi-clock fs-4"></i></div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="empty-state">Belum ada data monitoring.</div>
                    @endif
                </div>
            </div>

            <div class="panel mt-4">
                <div class="panel-header">
                    <div>
                        <h2 class="h5 fw-bold mb-0">Visualisasi BPM dan EKG</h2>
                        <div id="monitoringChartPatient" class="small text-secondary">{{ $latest?->patient?->name ?? 'Belum ada sesi' }}</div>
                    </div>
                    <span class="badge text-bg-light">Sesi #<span id="monitoringChartSession">{{ $latest?->id ?? '-' }}</span></span>
                </div>
                <div class="panel-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h3 class="h6 fw-bold mb-0">BPM</h3>
                        <div class="btn-group" aria-label="Kontrol zoom grafik BPM">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-zoom-chart="bpm" data-zoom-action="in" title="Perbesar grafik BPM"><i class="bi bi-zoom-in"></i></button>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-zoom-chart="bpm" data-zoom-action="out" title="Perkecil grafik BPM"><i class="bi bi-zoom-out"></i></button>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-zoom-chart="bpm" data-zoom-action="reset" title="Reset zoom grafik BPM"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </div>
                    </div>
                    <div class="chart-box mb-4"><canvas id="monitoringBpmChart"></canvas></div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h3 class="h6 fw-bold mb-0">Sinyal EKG</h3>
                        <div class="btn-group" aria-label="Kontrol zoom grafik EKG">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-zoom-chart="ekg" data-zoom-action="in" title="Perbesar grafik EKG"><i class="bi bi-zoom-in"></i></button>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-zoom-chart="ekg" data-zoom-action="out" title="Perkecil grafik EKG"><i class="bi bi-zoom-out"></i></button>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-zoom-chart="ekg" data-zoom-action="reset" title="Reset zoom grafik EKG"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </div>
                    </div>
                    <div class="chart-box"><canvas id="monitoringEkgChart"></canvas></div>
                </div>
            </div>

            <div class="panel mt-4">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Aktivitas Terbaru</h2>
                </div>
                <div class="panel-body">
                    @include('recordings._sessions-table', ['sessions' => $sessions])
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const initialChartData = @json($initialChartData);
    const chartDataBaseUrl = @json(url('/api/recordings'));
    let monitoredSessionId = @json($latest?->id);
    let monitoringBpmChart = null;
    let monitoringEkgChart = null;

    function monitoringZoomOptions(minRange = 0.5) {
        return {
            limits: {
                x: { min: 'original', max: 'original', minRange }
            },
            pan: {
                enabled: true,
                mode: 'x',
                threshold: 8
            },
            zoom: {
                wheel: { enabled: true, modifierKey: 'ctrl', speed: 0.08 },
                pinch: { enabled: true },
                mode: 'x'
            }
        };
    }

    function rrPointsFromPeaks(rPeaks, sampleRate) {
        const points = [];
        for (let index = 1; index < rPeaks.length; index++) {
            const rr = (Number(rPeaks[index]) - Number(rPeaks[index - 1])) / sampleRate;
            if (Number.isFinite(rr) && rr > 0) {
                points.push({ x: Number(rPeaks[index]) / sampleRate, y: 60 / rr });
            }
        }
        return points;
    }

    function bpmPoints(data) {
        const peaks = rrPointsFromPeaks(data.r_peaks || [], Number(data.sample_rate) || 360);
        if (peaks.length) return peaks;

        return (data.heart_rate || []).map((value, index) => {
            if (typeof value === 'object' && value !== null) {
                return { x: Number(value.x), y: Number(value.y) };
            }
            return { x: index + 1, y: Number(value) };
        }).filter(point => Number.isFinite(point.x) && Number.isFinite(point.y));
    }

    function signalPoints(values, sampleRate) {
        return (values || []).map((value, index) => ({ x: index / sampleRate, y: Number(value) }));
    }

    function createMonitoringBpmChart(points) {
        const canvas = document.getElementById('monitoringBpmChart');
        if (!canvas) return null;

        return new Chart(canvas, {
            type: 'line',
            data: { datasets: [{ label: 'BPM', data: points, borderColor: '#0a84c1', backgroundColor: 'rgba(10, 132, 193, .12)', fill: true, pointRadius: points.length > 250 ? 0 : 2, borderWidth: 1.5, tension: .2 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                parsing: false,
                normalized: true,
                interaction: { mode: 'nearest', intersect: false },
                plugins: {
                    legend: { display: false },
                    decimation: { enabled: true, algorithm: 'lttb', samples: 900, threshold: 1500 },
                    zoom: monitoringZoomOptions(0.5)
                },
                scales: {
                    x: { type: 'linear', title: { display: true, text: 'Waktu (s)' }, grid: { display: false } },
                    y: { title: { display: true, text: 'BPM' }, grid: { color: '#e5eef3' } }
                }
            }
        });
    }

    function createMonitoringEkgChart(data) {
        const canvas = document.getElementById('monitoringEkgChart');
        if (!canvas) return null;

        const sampleRate = Number(data.sample_rate) || 360;
        const raw = data.raw_signal || [];
        const filtered = (data.filtered_signal || []).length ? data.filtered_signal : raw;
        const rPeakPoints = (data.r_peaks || [])
            .filter(index => filtered[index] !== undefined)
            .map(index => ({ x: Number(index) / sampleRate, y: Number(filtered[index]) + 0.035 }));

        return new Chart(canvas, {
            type: 'line',
            data: {
                datasets: [
                    { label: 'Raw', data: signalPoints(raw, sampleRate), borderColor: 'rgba(150, 158, 166, .42)', pointRadius: 0, borderWidth: 1, tension: .08 },
                    { label: 'Filtered', data: signalPoints(filtered, sampleRate), borderColor: '#1f77b4', pointRadius: 0, borderWidth: 1.35, tension: .08 },
                    { label: 'R-peaks', type: 'scatter', data: rPeakPoints, borderColor: '#e2364f', backgroundColor: '#e2364f', pointStyle: 'triangle', pointRotation: 180, pointRadius: 4 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                parsing: false,
                normalized: true,
                interaction: { mode: 'nearest', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'start' },
                    decimation: { enabled: true, algorithm: 'lttb', samples: 1200, threshold: 2000 },
                    zoom: monitoringZoomOptions(0.25)
                },
                scales: {
                    x: { type: 'linear', title: { display: true, text: 'Waktu (s)' }, ticks: { callback: value => `${Number(value).toFixed(1)}s`, maxRotation: 0 }, grid: { color: 'rgba(16, 42, 53, .08)' } },
                    y: { title: { display: true, text: 'Amplitudo' }, grid: { color: 'rgba(16, 42, 53, .08)' } }
                }
            }
        });
    }

    function renderMonitoringCharts(data) {
        monitoringBpmChart?.destroy();
        monitoringEkgChart?.destroy();
        monitoringBpmChart = createMonitoringBpmChart(bpmPoints(data));
        monitoringEkgChart = createMonitoringEkgChart(data);
    }

    async function loadMonitoringCharts(sessionId) {
        if (!sessionId) return;
        const response = await fetch(`${chartDataBaseUrl}/${sessionId}/chart-data`, {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        });
        if (!response.ok) throw new Error('Data grafik tidak dapat dimuat.');
        renderMonitoringCharts(await response.json());
    }

    document.querySelectorAll('[data-zoom-chart][data-zoom-action]').forEach(button => {
        button.addEventListener('click', () => {
            const chart = button.dataset.zoomChart === 'bpm' ? monitoringBpmChart : monitoringEkgChart;
            if (!chart) return;

            if (button.dataset.zoomAction === 'in') chart.zoom({ x: 1.2 });
            if (button.dataset.zoomAction === 'out') chart.zoom({ x: 0.8 });
            if (button.dataset.zoomAction === 'reset') chart.resetZoom();
        });
    });

    renderMonitoringCharts(initialChartData);
    if (monitoredSessionId) {
        loadMonitoringCharts(monitoredSessionId).catch(() => {});
    }

    const initialClassification = @json($classification);
    const classificationCanvas = document.getElementById('classificationChart');
    const classificationChart = classificationCanvas ? new Chart(classificationCanvas, {
        type: 'doughnut',
        data: {
            labels: ['AF', 'Non-AF', 'Belum diklasifikasi'],
            datasets: [{
                data: [initialClassification.af, initialClassification.non_af, initialClassification.unclassified],
                backgroundColor: ['#d84c6f', '#1e9b67', '#7b8b94'],
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: context => `${context.label}: ${context.parsed} pasien`
                    }
                }
            }
        }
    }) : null;

    function updateClassification(summary) {
        if (!summary) return;

        const af = Number(summary.af || 0);
        const nonAf = Number(summary.non_af || 0);
        const unclassified = Number(summary.unclassified || 0);
        document.getElementById('classificationAf').textContent = af;
        document.getElementById('classificationNonAf').textContent = nonAf;
        document.getElementById('classificationPending').textContent = unclassified;
        document.getElementById('classificationTotal').textContent = Number(summary.total || 0);

        if (classificationChart) {
            classificationChart.data.datasets[0].data = [af, nonAf, unclassified];
            classificationChart.update('none');
        }
    }

    async function refreshMonitoring() {
        const lastCheck = document.getElementById('lastCheck');
        try {
            const response = await fetch('{{ route('monitoring.latest') }}', {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });
            const payload = await response.json();
            lastCheck.textContent = payload.checked_at || '-';

            const databaseStatus = document.getElementById('databaseStatus');
            if (payload.ok) {
                databaseStatus.textContent = 'Connected';
                databaseStatus.className = 'badge text-bg-success';

                let onlineCount = 0;
                let offlineCount = 0;
                (payload.devices || []).forEach(device => {
                    const deviceRow = document.querySelector(`[data-device-id="${device.id}"]`);
                    if (!deviceRow) return;

                    const statusBadge = deviceRow.querySelector('[data-device-status]');
                    const lastSeen = deviceRow.querySelector('[data-device-last-seen]');
                    if (statusBadge) {
                        statusBadge.textContent = device.status_label || 'Tidak diketahui';
                        statusBadge.className = `badge ${device.status_class || 'text-bg-secondary'}`;
                    }
                    if (lastSeen) lastSeen.textContent = device.last_seen_at || '-';

                    if (device.status === 'online') onlineCount++;
                    if (device.status === 'offline') offlineCount++;
                });

                const onlineDeviceCount = document.getElementById('onlineDeviceCount');
                const offlineDeviceCount = document.getElementById('offlineDeviceCount');
                if (onlineDeviceCount) onlineDeviceCount.textContent = onlineCount;
                if (offlineDeviceCount) offlineDeviceCount.textContent = offlineCount;
                updateClassification(payload.classification);

                if (payload.latest) {
                    const latestName = document.getElementById('latestName');
                    const latestBpm = document.getElementById('latestBpm');
                    const latestTime = document.getElementById('latestTime');
                    if (latestName) latestName.textContent = payload.latest.patient || '-';
                    if (latestBpm) latestBpm.textContent = payload.latest.bpm || '0';
                    if (latestTime) latestTime.textContent = payload.latest.recorded_at || '-';

                    if (Number(payload.latest.id) !== Number(monitoredSessionId)) {
                        monitoredSessionId = payload.latest.id;
                        document.getElementById('monitoringChartSession').textContent = payload.latest.id;
                        document.getElementById('monitoringChartPatient').textContent = payload.latest.patient || '-';
                        loadMonitoringCharts(payload.latest.id).catch(() => {});
                    }
                }
            } else {
                databaseStatus.textContent = 'Check';
                databaseStatus.className = 'badge text-bg-warning';
            }
        } catch (error) {
            lastCheck.textContent = 'request failed';
            const databaseStatus = document.getElementById('databaseStatus');
            databaseStatus.textContent = 'Check';
            databaseStatus.className = 'badge text-bg-warning';
        }
    }

    refreshMonitoring();
    window.setInterval(refreshMonitoring, 10000);
</script>
@endpush
