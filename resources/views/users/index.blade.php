@extends('layouts.app')

@section('title', 'Manajemen User')
@section('page_title', 'Manajemen User')
@section('page_subtitle', 'Akses pengguna')

@section('content')
    <div class="panel">
        <div class="panel-header">
            <h2 class="h5 fw-bold mb-0">Daftar User</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUser">
                <i class="bi bi-plus-lg me-1"></i>Tambah
            </button>
        </div>
        <div class="panel-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-lg-5">
                    <label class="form-label small text-secondary">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Nama, email, atau puskesmas">
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label small text-secondary">Role</label>
                    <select name="role" class="form-select">
                        <option value="">Semua</option>
                        <option value="super_admin" @selected(request('role') === 'super_admin')>Super Admin</option>
                        <option value="admin_puskesmas" @selected(request('role') === 'admin_puskesmas')>Admin Puskesmas</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small text-secondary">Tampil</label>
                    <select name="per_page" class="form-select">
                        @foreach ([5, 10, 25, 50] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 10) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-grid">
                    <button class="btn btn-primary" type="submit" title="Terapkan filter"><i class="bi bi-funnel"></i></button>
                </div>
            </form>

            <div class="table-responsive mt-3">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Puskesmas</th>
                            <th>Dibuat</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $managedUser)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $managedUser->name }}</div>
                                    <div class="small text-secondary">{{ $managedUser->email }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $managedUser->isSuperAdmin() ? 'text-bg-primary' : 'text-bg-info' }}">
                                        {{ $managedUser->isSuperAdmin() ? 'Super Admin' : 'Admin Puskesmas' }}
                                    </span>
                                </td>
                                <td>{{ $managedUser->puskesmas?->name ?? '-' }}</td>
                                <td>{{ $managedUser->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editUser{{ $managedUser->id }}" title="Edit user"><i class="bi bi-pencil"></i></button>
                                        <form method="POST" action="{{ route('users.destroy', $managedUser) }}" onsubmit="return confirm('Hapus user ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger rounded-start-0" title="Hapus user" @disabled(auth()->id() === $managedUser->id)><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty-state">Belum ada user sesuai filter.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @include('partials.pagination', ['records' => $users])
        </div>
    </div>

    <div class="modal fade" id="createUser" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h5">Tambah User</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body row g-3">
                    @include('users._form', ['managedUser' => null, 'passwordRequired' => true])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>

    @foreach ($users as $managedUser)
        <div class="modal fade" id="editUser{{ $managedUser->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('users.update', $managedUser) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h2 class="modal-title h5">Edit User</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body row g-3">
                        @include('users._form', ['managedUser' => $managedUser, 'passwordRequired' => false])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
