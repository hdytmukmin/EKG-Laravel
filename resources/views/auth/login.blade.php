<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login EKG Monitoring</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --ink: #102a35;
            --muted: #647d89;
            --accent: #087f8c;
            --accent-2: #12a3a8;
            --line: #d9e8ec;
            --soft: #e4f6f7;
        }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            margin: 0;
            background:
                radial-gradient(circle at 18% 12%, rgba(18, 163, 168, .18), transparent 24rem),
                radial-gradient(circle at 84% 88%, rgba(8, 127, 140, .14), transparent 24rem),
                linear-gradient(135deg, #eef8f9 0%, #f8fbfc 54%, #edf4f7 100%);
            color: var(--ink);
            font-family: "Plus Jakarta Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            letter-spacing: 0;
            padding: 22px;
        }
        .login-shell {
            width: min(980px, 100%);
            display: grid;
            grid-template-columns: minmax(0, .95fr) minmax(380px, .8fr);
            background: rgba(255,255,255,.76);
            border: 1px solid rgba(217, 232, 236, .9);
            border-radius: 28px;
            box-shadow: 0 26px 70px rgba(17, 71, 86, .13);
            overflow: hidden;
            backdrop-filter: blur(18px);
        }
        .login-visual {
            min-height: 560px;
            padding: 42px;
            color: #fff;
            background:
                radial-gradient(circle at 20% 10%, rgba(139, 224, 223, .34), transparent 18rem),
                linear-gradient(160deg, #073b4c, #032632);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .brand { display: flex; align-items: center; gap: 14px; font-family: "Sora", sans-serif; font-weight: 800; font-size: 1.4rem; }
        .brand-icon {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            color: #fff;
            background: linear-gradient(135deg, rgba(255,255,255,.22), rgba(255,255,255,.08));
            border: 1px solid rgba(255,255,255,.28);
            border-radius: 18px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.2), 0 18px 36px rgba(0,0,0,.2);
        }
        .visual-copy h1 {
            max-width: 430px;
            font-family: "Sora", sans-serif;
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-weight: 800;
            letter-spacing: 0;
            line-height: 1.06;
            margin-bottom: 16px;
        }
        .visual-copy p { max-width: 430px; margin: 0; color: rgba(255,255,255,.72); font-weight: 600; line-height: 1.7; }
        .signal-card {
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 22px;
            padding: 18px;
            background: rgba(255,255,255,.08);
        }
        .signal-line {
            height: 70px;
            background:
                linear-gradient(90deg, rgba(255,255,255,.1) 1px, transparent 1px) 0 0 / 34px 100%,
                linear-gradient(180deg, rgba(255,255,255,.1) 1px, transparent 1px) 0 0 / 100% 23px;
            position: relative;
            overflow: hidden;
        }
        .signal-line::after {
            content: "";
            position: absolute;
            inset: 8px 0;
            background: linear-gradient(90deg, transparent 0 8%, #8be0df 8% 10%, transparent 10% 18%, #8be0df 18% 22%, transparent 22% 35%, #8be0df 35% 37%, transparent 37% 47%, #8be0df 47% 55%, transparent 55% 70%, #8be0df 70% 73%, transparent 73% 100%);
            filter: drop-shadow(0 0 8px rgba(139,224,223,.55));
        }
        .login-panel {
            padding: 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: rgba(255,255,255,.92);
        }
        .login-panel h2 { font-family: "Sora", sans-serif; font-weight: 800; letter-spacing: 0; }
        .login-panel .text-secondary { color: var(--muted) !important; font-weight: 600; }
        .form-label { color: #526c7c; font-weight: 800; font-size: .86rem; }
        .form-control {
            border-color: #cfe0e6;
            border-radius: 14px;
            padding: .78rem .86rem;
            font-weight: 650;
            background: #fbfdfe;
        }
        .form-control:focus {
            border-color: var(--accent-2);
            box-shadow: 0 0 0 .22rem rgba(18, 163, 168, .14);
        }
        .form-check-input { border-color: #adc8d2; }
        .form-check-input:checked { background-color: var(--accent); border-color: var(--accent); }
        .btn {
            border-radius: 14px;
            font-weight: 800;
            padding: .78rem 1rem;
        }
        .btn-primary {
            --bs-btn-bg: var(--accent);
            --bs-btn-border-color: var(--accent);
            --bs-btn-hover-bg: #066a75;
            --bs-btn-hover-border-color: #066a75;
            box-shadow: 0 14px 26px rgba(8, 127, 140, .2);
        }
        .alert { border-radius: 14px; font-weight: 650; }
        @media (max-width: 860px) {
            .login-shell { grid-template-columns: 1fr; }
            .login-visual { min-height: auto; gap: 74px; padding: 32px; }
            .login-panel { padding: 32px; }
        }
        @media (max-width: 520px) {
            body { padding: 0; }
            .login-shell { min-height: 100vh; border-radius: 0; }
            .login-visual { padding: 24px; }
            .login-panel { padding: 24px; }
        }
    </style>
</head>
<body>
    <main class="login-shell">
        <section class="login-visual">
            <div class="brand">
                <div class="brand-icon"><i class="bi bi-heart-pulse fs-2"></i></div>
                <span>EKG Monitoring</span>
            </div>
            <div class="visual-copy">
                <h1>Monitoring EKG</h1>
                <p>Klasifikasi AF / Non-AF</p>
            </div>
            <div class="signal-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="fw-bold">Sinyal EKG</span>
                    <span class="badge text-bg-success rounded-pill">Aktif</span>
                </div>
                <div class="signal-line"></div>
            </div>
        </section>

        <section class="login-panel">
            <div class="mb-4">
                <h2 class="h3 mb-2">Masuk Admin</h2>
                <div class="text-secondary">Akses dashboard EKG</div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="vstack gap-3">
                @csrf
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control" autocomplete="username" required autofocus>
                </div>
                <div>
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" autocomplete="current-password" required>
                </div>
                <label class="form-check">
                    <input type="checkbox" name="remember" class="form-check-input">
                    <span class="form-check-label">Ingat saya</span>
                </label>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Login</button>
            </form>
        </section>
    </main>
</body>
</html>
