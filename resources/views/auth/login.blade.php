<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login EKG Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: grid; place-items: center; background: #eef7fb; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        .login-panel { width: min(420px, calc(100vw - 32px)); background: #fff; border: 1px solid #d7e6ee; border-radius: 8px; padding: 28px; box-shadow: 0 18px 44px rgba(17, 66, 90, .12); }
        .brand-icon { width: 54px; height: 54px; display: grid; place-items: center; color: #0a84c1; background: #e8f4fa; border-radius: 8px; }
    </style>
</head>
<body>
    <main class="login-panel">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="brand-icon"><i class="bi bi-heart-pulse fs-3"></i></div>
            <div>
                <h1 class="h4 fw-bold mb-0">EKG Monitoring</h1>
                <div class="text-secondary">AF / Non-AF dashboard</div>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" class="vstack gap-3">
            @csrf
            <div>
                <label class="form-label">Email</label>
                <input type="email" name="email" value="{{ old('email', 'superadmin@ekg.local') }}" class="form-control" required autofocus>
            </div>
            <div>
                <label class="form-label">Password</label>
                <input type="password" name="password" value="password" class="form-control" required>
            </div>
            <label class="form-check">
                <input type="checkbox" name="remember" class="form-check-input">
                <span class="form-check-label">Ingat saya</span>
            </label>
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Login</button>
        </form>
    </main>
</body>
</html>
