<form method="GET" class="row g-2 align-items-end">
    <div class="col-12 col-lg-4">
        <label class="form-label small text-secondary">Search</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Nama, alamat, BPM, tanggal">
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <label class="form-label small text-secondary">Gender</label>
        <input name="jk" class="form-control" value="{{ request('jk') }}" placeholder="L/P">
    </div>
    <div class="col-6 col-lg-2">
        <label class="form-label small text-secondary">Status</label>
        <select name="status" class="form-select">
            <option value="">Semua</option>
            <option value="complete" @selected(request('status') === 'complete')>Lengkap</option>
            <option value="empty" @selected(request('status') === 'empty')>Belum lengkap</option>
        </select>
    </div>
    <div class="col-6 col-lg-1">
        <label class="form-label small text-secondary">BPM min</label>
        <input type="number" step="0.1" name="bpm_min" class="form-control" value="{{ request('bpm_min') }}">
    </div>
    <div class="col-6 col-lg-1">
        <label class="form-label small text-secondary">BPM max</label>
        <input type="number" step="0.1" name="bpm_max" class="form-control" value="{{ request('bpm_max') }}">
    </div>
    <div class="col-6 col-lg-1">
        <label class="form-label small text-secondary">Tampil</label>
        <select name="per_page" class="form-select">
            @foreach ([5, 10, 25, 50] as $size)
                <option value="{{ $size }}" @selected((int) request('per_page', 10) === $size)>{{ $size }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-lg-1 d-grid">
        <button class="btn btn-primary" type="submit" title="Terapkan filter"><i class="bi bi-funnel"></i></button>
    </div>
</form>
