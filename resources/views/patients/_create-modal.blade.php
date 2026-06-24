<div class="modal fade" id="addPatientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('patients.store') }}">
            @csrf
            <div class="modal-header">
                <h2 class="modal-title h5">Tambah Pasien</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama pasien</label>
                    <input name="name" class="form-control" required maxlength="255" placeholder="Contoh: AMH">
                </div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label">Umur</label><input name="age" type="number" min="0" max="120" class="form-control"></div>
                    <div class="col-6"><label class="form-label">Gender</label><input name="gender" class="form-control" placeholder="Laki-laki/Perempuan"></div>
                </div>
                <div class="mt-3"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="2"></textarea></div>
                <div class="form-text mt-2">Pasien otomatis terikat ke puskesmas admin yang sedang login.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>
