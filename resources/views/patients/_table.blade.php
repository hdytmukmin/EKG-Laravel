<div class="table-responsive mt-3">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Pasien</th>
                <th>Puskesmas</th>
                <th>Umur</th>
                <th>Gender</th>
                <th>Sesi</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($patients as $patient)
                <tr>
                    <td>#{{ $patient->id }}</td>
                    <td>
                        <div class="entity-cell">
                            <div class="entity-avatar"><i class="bi bi-person-heart"></i></div>
                            <div class="min-w-0">
                                <div class="fw-bold">{{ $patient->name }}</div>
                                <div class="small text-secondary text-truncate" style="max-width: 260px">{{ $patient->address ?: 'Alamat belum diisi' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $patient->puskesmas?->name ?? '-' }}</td>
                    <td>{{ $patient->age ?? '-' }}</td>
                    <td>{{ $patient->gender ?? '-' }}</td>
                    <td>{{ $patient->recording_sessions_count ?? $patient->recordingSessions()->count() }}</td>
                    <td class="text-end">
                        <div class="btn-group">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('patients.show', $patient) }}" title="Detail pasien"><i class="bi bi-eye"></i></a>
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editPatient{{ $patient->id }}" title="Edit pasien"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="{{ route('patients.destroy', $patient) }}" onsubmit="return confirm('Hapus pasien ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger rounded-start-0" title="Hapus pasien"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state">Belum ada pasien sesuai filter.</div></td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('partials.pagination', ['records' => $patients])

@foreach ($patients as $patient)
    <div class="modal fade" id="editPatient{{ $patient->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" action="{{ route('patients.update', $patient) }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h2 class="modal-title h5">Edit Pasien</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12"><label class="form-label">Nama</label><input name="name" class="form-control" value="{{ $patient->name }}" required></div>
                    <div class="col-6"><label class="form-label">Umur</label><input name="age" type="number" min="0" max="120" class="form-control" value="{{ $patient->age }}"></div>
                    <div class="col-6"><label class="form-label">Gender</label><input name="gender" class="form-control" value="{{ $patient->gender }}"></div>
                    <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="3">{{ $patient->address }}</textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
