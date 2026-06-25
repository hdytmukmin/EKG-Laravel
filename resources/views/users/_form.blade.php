<div class="col-12">
    <label class="form-label">Nama</label>
    <input name="name" class="form-control" value="{{ old('name', $managedUser?->name) }}" required>
</div>
<div class="col-12">
    <label class="form-label">Email</label>
    <input name="email" type="email" class="form-control" value="{{ old('email', $managedUser?->email) }}" required>
</div>
<div class="col-12">
    <label class="form-label">Role</label>
    <select name="role" class="form-select" required>
        <option value="admin_puskesmas" @selected(old('role', $managedUser?->role ?? 'admin_puskesmas') === 'admin_puskesmas')>Admin Puskesmas</option>
        <option value="super_admin" @selected(old('role', $managedUser?->role) === 'super_admin')>Super Admin</option>
    </select>
</div>
<div class="col-12">
    <label class="form-label">Puskesmas</label>
    <select name="puskesmas_id" class="form-select">
        <option value="">Tidak terikat puskesmas</option>
        @foreach ($puskesmasOptions as $puskesmas)
            <option value="{{ $puskesmas->id }}" @selected((int) old('puskesmas_id', $managedUser?->puskesmas_id) === $puskesmas->id)>
                {{ $puskesmas->name }}
            </option>
        @endforeach
    </select>
</div>
<div class="col-12">
    <label class="form-label">{{ $passwordRequired ? 'Password' : 'Password Baru' }}</label>
    <input name="password" type="password" class="form-control" minlength="6" @required($passwordRequired)>
    @unless ($passwordRequired)
        <div class="form-text">Kosongkan jika tidak ingin mengganti password.</div>
    @endunless
</div>
