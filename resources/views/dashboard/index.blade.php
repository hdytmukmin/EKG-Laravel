@extends('layouts.app')

@section('title', 'Dashboard EKG')
@section('page_title', 'Dashboard EKG')
@section('page_subtitle', $dashboard['is_super_admin'] ? 'Seluruh fasilitas kesehatan' : 'Fasilitas kesehatan Anda')

@section('content')
    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-4">
            <div class="panel h-100">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Status Sistem</h2>
                </div>
                <div class="panel-body">
                    <div class="status-list">
                        <div class="status-row">
                            <span>Database</span>
                            <span class="badge {{ $dbError ? 'text-bg-warning' : 'text-bg-success' }}">
                                {{ $dbError ? 'Perlu diperiksa' : 'Terhubung' }}
                            </span>
                        </div>
                        <div class="status-row">
                            <span>MQTT Broker</span>
                            <span class="badge text-bg-success">Terkonfigurasi</span>
                        </div>
                        <div class="status-row">
                            <span>Alat Terdaftar</span>
                            <span class="badge text-bg-primary">{{ $devices->count() }}</span>
                        </div>
                        <div class="status-row">
                            <span>Mode Aplikasi</span>
                            <span class="badge text-bg-primary">Laravel</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="panel h-100">
                <div class="panel-header">
                    <h2 class="h5 fw-bold mb-0">Breakdown Fasilitas Kesehatan</h2>
                </div>
                <div class="panel-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Fasilitas Kesehatan</th>
                                <th>Pasien</th>
                                <th>AF</th>
                                <th>Non-AF</th>
                                <th>Sesi</th>
                                <th>Rekaman Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($dashboard['breakdown'] as $site)
                                <tr>
                                    <td>{{ $site->name }}</td>
                                    <td>{{ $site->patients_count }}</td>
                                    <td><span class="badge text-bg-danger">{{ $site->af_patients }}</span></td>
                                    <td><span class="badge text-bg-success">{{ $site->non_af_patients }}</span></td>
                                    <td>{{ $site->recording_sessions_count }}</td>
                                    <td>{{ $site->latest_recorded_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6"><div class="empty-state">Belum ada data fasilitas kesehatan.</div></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2 class="h5 fw-bold mb-0">Sesi Rekaman Terbaru</h2>
        </div>
        <div class="panel-body">
            @include('recordings._sessions-table', ['sessions' => $sessions, 'showActions' => false])
        </div>
    </div>
@endsection
