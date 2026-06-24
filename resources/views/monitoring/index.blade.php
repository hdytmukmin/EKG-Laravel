@extends('layouts.app')

@section('title', 'Monitoring Service')
@section('page_title', 'Monitoring Service')
@section('page_subtitle', 'Ringkasan koneksi database, MQTT, dan data terakhir dari alat')

@section('content')
    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Status</h2>
                </div>
                <div class="panel-body">
                    <div class="d-flex justify-content-between border-bottom py-3">
                        <span>Database</span>
                        <span id="databaseStatus" class="badge {{ $dbError ? 'text-bg-warning' : 'text-bg-success' }}">{{ $dbError ? 'Check' : 'Connected' }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-3">
                        <span>MQTT Broker</span>
                        <span class="badge text-bg-success">Active</span>
                    </div>
                    <div class="d-flex justify-content-between py-3">
                        <span>Subscriber</span>
                        <span class="badge text-bg-primary">php artisan mqtt:listen</span>
                    </div>
                    <div class="small text-secondary mt-2">Last check: <span id="lastCheck">-</span></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-8">
            <div class="panel">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Data Terakhir</h2>
                    <span class="text-secondary small">Dari tabel recordekg</span>
                </div>
                <div class="panel-body">
                    @if ($latest)
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <div class="stat-card">
                                    <div>
                                        <div class="stat-label">Pasien</div>
                                        <div id="latestName" class="fw-bold fs-5">{{ $latest->nama ?: '-' }}</div>
                                    </div>
                                    <div class="stat-icon"><i class="bi bi-person-vcard fs-4"></i></div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="stat-card">
                                    <div>
                                        <div class="stat-label">BPM</div>
                                        <div id="latestBpm" class="stat-value">{{ $latest->bpm ?: 0 }}</div>
                                    </div>
                                    <div class="stat-icon"><i class="bi bi-heart-pulse fs-4"></i></div>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="stat-card">
                                    <div>
                                        <div class="stat-label">Tanggal</div>
                                        <div id="latestTime" class="fw-bold text-break">{{ $latest->tglrekam ?: '-' }}</div>
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
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-header">
            <h2 class="h5 fw-bold mb-0">Aktivitas Terbaru</h2>
        </div>
        <div class="panel-body">
            @include('patients._table', ['records' => $recentRows, 'showActions' => false])
        </div>
    </div>
@endsection

@push('scripts')
<script>
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

                if (payload.latest) {
                    const latestName = document.getElementById('latestName');
                    const latestBpm = document.getElementById('latestBpm');
                    const latestTime = document.getElementById('latestTime');
                    if (latestName) latestName.textContent = payload.latest.nama || '-';
                    if (latestBpm) latestBpm.textContent = payload.latest.bpm || '0';
                    if (latestTime) latestTime.textContent = payload.latest.tglrekam || '-';
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
