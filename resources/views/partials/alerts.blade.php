@if (session('success'))
    <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger border-0 shadow-sm">
        <strong>Validasi belum sesuai.</strong>
        <div>{{ $errors->first() }}</div>
    </div>
@endif

@isset($dbError)
    @if ($dbError)
        <div class="alert alert-warning border-0 shadow-sm">
            <strong>Koneksi database belum siap.</strong>
            <div class="small mt-1">{{ $dbError }}</div>
        </div>
    @endif
@endisset
