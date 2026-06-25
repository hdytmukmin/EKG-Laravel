<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'EKG Monitoring')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --sidebar: #073b4c;
            --sidebar-deep: #032632;
            --sidebar-glow: #0f7f8d;
            --surface: #eef6f8;
            --surface-2: #f8fbfc;
            --panel: #ffffff;
            --line: #d9e8ec;
            --line-strong: #bfd4dc;
            --ink: #102a35;
            --muted: #647d89;
            --accent: #087f8c;
            --accent-2: #12a3a8;
            --accent-soft: #e4f6f7;
            --danger: #d84c6f;
            --danger-soft: #fde8ef;
            --success: #1e9b67;
            --success-soft: #e6f7ef;
            --warning: #c88a12;
            --warning-soft: #fff4d7;
            --shadow: 0 18px 46px rgba(17, 71, 86, .09);
            --shadow-soft: 0 10px 24px rgba(17, 71, 86, .06);
        }
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(18, 163, 168, .16), transparent 34rem),
                linear-gradient(135deg, #eef8f9 0%, #f7fbfc 52%, #edf4f7 100%);
            color: var(--ink);
            font-family: "Plus Jakarta Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            letter-spacing: 0;
        }
        .app-shell { display: flex; min-height: 100vh; }
        .sidebar {
            width: 292px;
            flex: 0 0 292px;
            color: #fff;
            background:
                radial-gradient(circle at 18% 0%, rgba(31, 201, 203, .22), transparent 18rem),
                linear-gradient(180deg, var(--sidebar), var(--sidebar-deep));
            padding: 30px 20px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 18px 0 50px rgba(3, 38, 50, .14);
        }
        .brand { display: flex; align-items: center; gap: 14px; margin-bottom: 34px; font-family: "Sora", sans-serif; font-weight: 800; font-size: 1.32rem; letter-spacing: 0; }
        .brand-icon {
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(255,255,255,.22), rgba(255,255,255,.08));
            border: 1px solid rgba(255,255,255,.26);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.18), 0 16px 34px rgba(0,0,0,.18);
        }
        .nav-label { color: rgba(255,255,255,.64); font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; margin: 24px 14px 10px; font-weight: 800; }
        .side-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,.78);
            text-decoration: none;
            padding: 13px 14px;
            border-radius: 14px;
            margin-bottom: 7px;
            font-weight: 750;
            border: 1px solid transparent;
            transition: background-color .18s ease, color .18s ease, transform .18s ease, border-color .18s ease;
        }
        .side-link:hover {
            background: rgba(255,255,255,.11);
            border-color: rgba(255,255,255,.14);
            color: #fff;
            transform: translateX(2px);
        }
        .side-link.active {
            background: linear-gradient(135deg, rgba(255,255,255,.2), rgba(255,255,255,.1));
            border-color: rgba(255,255,255,.22);
            color: #fff;
            box-shadow: 0 12px 26px rgba(0,0,0,.14);
        }
        .side-link i { width: 24px; font-size: 1.14rem; color: #8be0df; }
        .side-link.active i { color: #fff; }
        .main { flex: 1; min-width: 0; }
        .topbar {
            background: rgba(255,255,255,.86);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid var(--line);
            padding: 24px 36px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .topbar h1 { font-family: "Sora", sans-serif; font-size: clamp(1.42rem, 2vw, 2.05rem); margin: 0; font-weight: 800; letter-spacing: 0; }
        .topbar p { color: var(--muted); margin: 6px 0 0; font-weight: 500; }
        .content { padding: 30px 36px 44px; }
        .user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--ink);
            background: #f7fbfc;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 8px 12px 8px 10px;
            box-shadow: 0 8px 22px rgba(17, 71, 86, .06);
        }
        .user-pill i {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            color: var(--accent);
            background: var(--accent-soft);
            border-radius: 999px;
        }
        .user-pill strong { display: block; font-size: .88rem; line-height: 1.1; }
        .user-pill span { color: var(--muted); display: block; font-size: .72rem; font-weight: 800; line-height: 1.1; text-transform: uppercase; letter-spacing: .04em; }
        .page-hero {
            position: relative;
            overflow: hidden;
            margin-bottom: 26px;
            padding: 28px;
            border: 1px solid rgba(255,255,255,.38);
            border-radius: 24px;
            color: #fff;
            background:
                radial-gradient(circle at 16% 0%, rgba(139, 224, 223, .34), transparent 22rem),
                radial-gradient(circle at 90% 20%, rgba(255, 255, 255, .12), transparent 18rem),
                linear-gradient(135deg, #073b4c, #075467 52%, #087f8c);
            box-shadow: var(--shadow);
        }
        .page-hero::after {
            content: "";
            position: absolute;
            inset: auto -12% -55% 35%;
            height: 240px;
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            transform: rotate(-8deg);
        }
        .page-hero > * { position: relative; z-index: 1; }
        .hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
            padding: 7px 11px;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 999px;
            background: rgba(255,255,255,.11);
            color: rgba(255,255,255,.82);
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .page-hero h2 {
            max-width: 740px;
            margin: 0;
            font-family: "Sora", sans-serif;
            font-size: clamp(1.7rem, 2.6vw, 2.75rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: 0;
        }
        .page-hero p { max-width: 680px; margin: 12px 0 0; color: rgba(255,255,255,.74); font-weight: 600; line-height: 1.7; }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }
        .hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 13px;
            border-radius: 999px;
            color: #fff;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.18);
            font-weight: 750;
        }
        .hero-chip i { color: #8be0df; }
        .panel, .stat-card {
            background: rgba(255,255,255,.94);
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: var(--shadow-soft);
        }
        .filter-panel {
            border-radius: 22px;
            background:
                linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,251,252,.92));
            box-shadow: var(--shadow-soft);
        }
        .filter-panel .panel-body { padding: 20px; }
        .table-toolbar {
            margin-bottom: 18px;
            padding: 16px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #f8fbfc;
        }
        .panel-header {
            padding: 20px 22px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            background: linear-gradient(180deg, rgba(248,251,252,.86), rgba(255,255,255,.72));
            border-radius: 18px 18px 0 0;
        }
        .panel-header h2 { font-family: "Sora", sans-serif; letter-spacing: 0; }
        .panel-body { padding: 22px; }
        .stat-card { padding: 21px; min-height: 130px; display: flex; align-items: center; justify-content: space-between; gap: 16px; position: relative; overflow: hidden; }
        .stat-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: linear-gradient(180deg, var(--accent-2), var(--accent));
        }
        .stat-card::after {
            content: "";
            position: absolute;
            right: -30px;
            bottom: -44px;
            width: 116px;
            height: 116px;
            border-radius: 999px;
            background: rgba(18, 163, 168, .08);
        }
        .stat-card > * { position: relative; z-index: 1; }
        .stat-card.stat-danger::before { background: linear-gradient(180deg, #f07b98, var(--danger)); }
        .stat-card.stat-danger .stat-icon { color: var(--danger); background: linear-gradient(135deg, var(--danger-soft), #fff7fa); border-color: #f6cbd8; }
        .stat-card.stat-success::before { background: linear-gradient(180deg, #5cd39c, var(--success)); }
        .stat-card.stat-success .stat-icon { color: var(--success); background: linear-gradient(135deg, var(--success-soft), #f6fffa); border-color: #c7ebda; }
        .stat-card.stat-warning::before { background: linear-gradient(180deg, #f0bd4f, var(--warning)); }
        .stat-card.stat-warning .stat-icon { color: var(--warning); background: linear-gradient(135deg, var(--warning-soft), #fffaf0); border-color: #f0ddb0; }
        .stat-label { color: var(--muted); margin-bottom: 10px; font-weight: 700; }
        .stat-value { font-family: "Sora", sans-serif; font-size: 2rem; font-weight: 800; line-height: 1.1; word-break: break-word; }
        .stat-icon {
            width: 56px;
            height: 56px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            color: var(--accent);
            background: linear-gradient(135deg, var(--accent-soft), #f2fbfb);
            flex: 0 0 auto;
            border: 1px solid #cfebee;
        }
        .table { vertical-align: middle; }
        .table-responsive {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
        }
        .table-responsive .table { margin-bottom: 0; }
        .table thead th { color: #526c7c; font-size: .76rem; text-transform: uppercase; letter-spacing: .08em; background: #f3f8fa; padding-top: 14px; padding-bottom: 14px; border-bottom: 1px solid var(--line); }
        .table tbody td { padding-top: 15px; padding-bottom: 15px; border-color: #e5eff2; }
        .table-hover tbody tr:hover { background-color: #f6fbfc; }
        .table tbody tr:last-child td { border-bottom: 0; }
        .entity-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .entity-avatar {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 12px;
            color: var(--accent);
            background: var(--accent-soft);
            border: 1px solid #cfebee;
        }
        .entity-avatar.danger { color: var(--danger); background: var(--danger-soft); border-color: #f6cbd8; }
        .entity-avatar.success { color: var(--success); background: var(--success-soft); border-color: #c7ebda; }
        .chart-box { height: 310px; }
        .ekg-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
            padding: 12px;
        }
        .ekg-scroll canvas { display: block; }
        .empty-state { padding: 34px; text-align: center; color: var(--muted); font-weight: 650; border: 1px dashed var(--line-strong); border-radius: 16px; background: #f8fbfc; }
        .badge { border-radius: 999px; padding: .46rem .68rem; font-weight: 800; letter-spacing: 0; }
        .btn { border-radius: 12px; font-weight: 750; padding: .62rem .92rem; }
        .btn-sm { border-radius: 10px; padding: .38rem .58rem; }
        .btn-primary {
            --bs-btn-bg: var(--accent);
            --bs-btn-border-color: var(--accent);
            --bs-btn-hover-bg: #066a75;
            --bs-btn-hover-border-color: #066a75;
            --bs-btn-active-bg: #055964;
            --bs-btn-active-border-color: #055964;
            box-shadow: 0 10px 22px rgba(8, 127, 140, .18);
        }
        .btn-outline-primary {
            --bs-btn-color: var(--accent);
            --bs-btn-border-color: #9ccfd4;
            --bs-btn-hover-bg: var(--accent);
            --bs-btn-hover-border-color: var(--accent);
        }
        .form-control, .form-select, .input-group-text {
            border-color: #cfe0e6;
            border-radius: 12px;
            padding: .66rem .78rem;
            font-weight: 600;
        }
        .input-group .form-control { border-top-left-radius: 0; border-bottom-left-radius: 0; }
        .input-group-text { background: #f3f8fa; color: var(--accent); }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-2);
            box-shadow: 0 0 0 .22rem rgba(18, 163, 168, .14);
        }
        .modal-content { border: 1px solid var(--line); border-radius: 18px; box-shadow: var(--shadow); }
        .modal-header, .modal-footer { border-color: var(--line); }
        .text-secondary { color: var(--muted) !important; }
        .status-list {
            display: grid;
            gap: 12px;
        }
        .status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 15px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #f9fcfd;
        }
        .status-row strong { display: block; }
        .status-row span:first-child { color: var(--muted); font-weight: 700; }
        @media (max-width: 991.98px) {
            .app-shell { display: block; }
            .sidebar { width: auto; height: auto; position: static; padding: 18px; }
            .brand { margin-bottom: 18px; }
            .side-nav { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
            .side-link { margin: 0; }
            .topbar, .content { padding-left: 18px; padding-right: 18px; }
            .topbar { position: static; }
        }
        @media (max-width: 575.98px) {
            .side-nav { grid-template-columns: 1fr; }
            .topbar { align-items: flex-start; flex-direction: column; }
            .page-hero { padding: 22px; border-radius: 18px; }
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
                    <i class="bi bi-grid-1x2"></i><span>Dashboard</span>
                </a>
                <a class="side-link {{ request()->routeIs('patients.*') ? 'active' : '' }}" href="{{ route('patients.index') }}">
                    <i class="bi bi-person-vcard"></i><span>Pasien</span>
                </a>
                <a class="side-link {{ request()->routeIs('recordings.*') ? 'active' : '' }}" href="{{ route('recordings.index') }}">
                    <i class="bi bi-clipboard2-pulse"></i><span>Rekaman EKG</span>
                </a>
                <a class="side-link {{ request()->routeIs('monitoring.*') ? 'active' : '' }}" href="{{ route('monitoring.index') }}">
                    <i class="bi bi-broadcast"></i><span>Monitoring</span>
                </a>
                <a class="side-link {{ request()->routeIs('devices.*') ? 'active' : '' }}" href="{{ route('devices.index') }}">
                    <i class="bi bi-cpu"></i><span>Alat EKG</span>
                </a>
                @if (auth()->user()?->isSuperAdmin())
                    <a class="side-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                        <i class="bi bi-shield-lock"></i><span>User</span>
                    </a>
                @endif
            </nav>
            <div class="nav-label">Service</div>
            <div class="px-3 small text-white-50"><i class="bi bi-circle-fill text-success me-2"></i>MQTT Broker</div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div>
                    <h1>@yield('page_title', 'Dashboard EKG')</h1>
                    <p>@yield('page_subtitle', 'Ringkasan pasien, rekaman, dan indikator denyut jantung')</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="user-pill">
                        <i class="bi bi-person-circle"></i>
                        <div>
                            <strong>{{ auth()->user()->name ?? 'Guest' }}</strong>
                            <span>{{ auth()->user()?->isSuperAdmin() ? 'Super Admin' : 'Admin Puskesmas' }}</span>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary" type="submit" title="Logout"><i class="bi bi-door-open"></i></button>
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
