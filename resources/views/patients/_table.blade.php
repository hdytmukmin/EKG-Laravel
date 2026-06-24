<div class="table-responsive mt-3">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Pasien</th>
                <th>Gender</th>
                <th>BPM</th>
                <th>TSPT</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                @php($status = $record->status())
                <tr>
                    <td class="text-secondary">#{{ $record->id }}</td>
                    <td>
                        <div class="fw-bold">{{ $record->nama ?: '-' }}</div>
                        <div class="small text-secondary text-truncate" style="max-width: 240px">{{ $record->alamat ?: 'Alamat belum diisi' }}</div>
                    </td>
                    <td>{{ $record->jk ?: '-' }}</td>
                    <td class="fw-bold">{{ $record->bpm ?: '0' }}</td>
                    <td>{{ $record->tspt ?: '0' }}</td>
                    <td>{{ $record->tglrekam ?: '-' }}</td>
                    <td><span class="badge {{ $status['class'] }}"><i class="bi {{ $status['icon'] }} me-1"></i>{{ $status['label'] }}</span></td>
                    <td class="text-end">
                        <div class="btn-group">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('recordings.show', $record) }}" title="Lihat detail"><i class="bi bi-eye"></i></a>
                            @if ($showActions ?? true)
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editPatient{{ $record->id }}" title="Edit pasien"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#uploadPatient{{ $record->id }}" title="Upload CSV"><i class="bi bi-upload"></i></button>
                                <form method="POST" action="{{ route('patients.destroy', $record) }}" onsubmit="return confirm('Hapus data pasien ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger rounded-start-0" title="Hapus pasien"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Belum ada data sesuai filter.
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('partials.pagination', ['records' => $records])

@if ($showActions ?? true)
    @foreach ($records as $record)
        <div class="modal fade" id="editPatient{{ $record->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('patients.update', $record) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h2 class="modal-title h5">Edit Pasien</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body row g-3">
                        <div class="col-12">
                            <label class="form-label">Nama</label>
                            <input name="nama" class="form-control" value="{{ $record->nama }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Umur</label>
                            <input name="umur" type="number" min="0" max="120" class="form-control" value="{{ $record->umur }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Gender</label>
                            <input name="jk" class="form-control" value="{{ $record->jk }}" placeholder="L/P">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tanggal rekam</label>
                            <input name="tglrekam" class="form-control" value="{{ $record->tglrekam }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alamat</label>
                            <textarea name="alamat" class="form-control" rows="3">{{ $record->alamat }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="uploadPatient{{ $record->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" enctype="multipart/form-data" action="{{ route('patients.upload', $record) }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5">Upload CSV EKG</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2 fw-bold">{{ $record->nama }}</div>
                        <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                        <div class="form-text">File diproses oleh Laravel melalui processor EKG yang sudah dimigrasikan.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-upload me-1"></i>Upload</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endif
