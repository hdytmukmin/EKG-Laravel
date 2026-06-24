<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'EKG Monitoring')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --sidebar: #0b4f6c;
            --sidebar-deep: #06384c;
            --surface: #f3f8fb;
            --line: #d7e6ee;
            --ink: #102230;
            --muted: #627888;
            --accent: #0a84c1;
        }
        body {
            min-height: 100vh;
            background: var(--surface);
            color: var(--ink);
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .app-shell { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px;
            flex: 0 0 280px;
            color: #fff;
            background: linear-gradient(180deg, var(--sidebar), var(--sidebar-deep));
            padding: 28px 20px;
            position: sticky;
            top: 0;
            height: 100vh;
        }
        .brand { display: flex; align-items: center; gap: 14px; margin-bottom: 34px; font-weight: 800; font-size: 1.35rem; }
        .brand-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.22);
        }
        .nav-label { color: rgba(255,255,255,.72); font-size: .78rem; letter-spacing: .08em; text-transform: uppercase; margin: 22px 14px 10px; }
        .side-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,.84);
            text-decoration: none;
            padding: 13px 14px;
            border-radius: 8px;
            margin-bottom: 6px;
            font-weight: 650;
        }
        .side-link:hover, .side-link.active { background: rgba(255,255,255,.15); color: #fff; }
        .side-link i { width: 22px; font-size: 1.15rem; }
        .main { flex: 1; min-width: 0; }
        .topbar {
            background: #fff;
            border-bottom: 1px solid var(--line);
            padding: 22px 34px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }
        .topbar h1 { font-size: clamp(1.35rem, 2vw, 2rem); margin: 0; font-weight: 800; letter-spacing: 0; }
        .topbar p { color: var(--muted); margin: 4px 0 0; }
        .content { padding: 28px 34px 40px; }
        .panel, .stat-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 8px 22px rgba(19, 55, 74, .05);
        }
        .panel-header {
            padding: 20px 22px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }
        .panel-body { padding: 22px; }
        .stat-card { padding: 20px; min-height: 126px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .stat-label { color: var(--muted); margin-bottom: 8px; }
        .stat-value { font-size: 1.9rem; font-weight: 850; line-height: 1.1; word-break: break-word; }
        .stat-icon {
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            color: var(--accent);
            background: #e8f4fa;
            flex: 0 0 auto;
        }
        .table { vertical-align: middle; }
        .table thead th { color: #526c7c; font-size: .82rem; text-transform: uppercase; letter-spacing: .04em; background: #f6fafc; }
        .chart-box { height: 310px; }
        .ekg-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 12px;
        }
        .ekg-scroll canvas { display: block; }
        .empty-state { padding: 34px; text-align: center; color: var(--muted); }
        .badge { border-radius: 6px; }
        @media (max-width: 991.98px) {
            .app-shell { display: block; }
            .sidebar { width: auto; height: auto; position: static; padding: 18px; }
            .brand { margin-bottom: 18px; }
            .side-nav { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
            .side-link { margin: 0; }
            .topbar, .content { padding-left: 18px; padding-right: 18px; }
        }
        @media (max-width: 575.98px) {
            .side-nav { grid-template-columns: 1fr; }
            .topbar { align-items: flex-start; flex-direction: column; }
            .stat-card { align-items: flex-start; }
            .chart-box { height: 260px; }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-icon"><i class="bi bi-heart-pulse fs-3"></i></div>
                <span>EKG Monitoring</span>
            </div>
            <div class="nav-label">Admin</div>
            <nav class="side-nav">
                <a class="side-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <i class="bi bi-speedometer2"></i><span>Dashboard</span>
                </a>
                <a class="side-link {{ request()->routeIs('patients.*') ? 'active' : '' }}" href="{{ route('patients.index') }}">
                    <i class="bi bi-people"></i><span>Pasien</span>
                </a>
                <a class="side-link {{ request()->routeIs('recordings.*') ? 'active' : '' }}" href="{{ route('recordings.index') }}">
                    <i class="bi bi-activity"></i><span>Rekaman EKG</span>
                </a>
                <a class="side-link {{ request()->routeIs('monitoring.*') ? 'active' : '' }}" href="{{ route('monitoring.index') }}">
                    <i class="bi bi-broadcast-pin"></i><span>Monitoring</span>
                </a>
                <a class="side-link {{ request()->routeIs('devices.*') ? 'active' : '' }}" href="{{ route('devices.index') }}">
                    <i class="bi bi-router"></i><span>Alat EKG</span>
                </a>
            </nav>
            <div class="nav-label">Service</div>
            <div class="px-3 small text-white-50"><i class="bi bi-circle-fill text-success me-2"></i>MQTT Broker</div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div>
                    <h1>@yield('page_title', 'Dashboard EKG')</h1>
                    <p>@yield('page_subtitle', 'Ringkasan pasien, rekaman, dan indikator denyu jantung')</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-secondary"><i class="bi bi-person-circle me-2"></i>{{ auth()->user()->name ?? 'Guest' }}</div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary" type="submit" title="Logout"><i class="bi bi-box-arrow-right"></i></button>
                    </form>
                </div>
            </header>
            <section class="content">
                @include('partials.alerts')
                @yield('content')
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
