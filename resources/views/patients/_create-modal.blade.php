<div class="modal fade" id="addPatientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('patients.store') }}">
            @csrf
            <div class="modal-header">
                <h2 class="modal-title h5">Tambah Pasien</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Nama pasien</label>
                <input name="nama" class="form-control" required maxlength="255" placeholder="Contoh: AMH">
                <div class="form-text">Nama ini dipakai untuk mencocokkan data yang masuk dari alat IoT.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Simpan</button>
            </div>
        </form>
    </div>
</div>
