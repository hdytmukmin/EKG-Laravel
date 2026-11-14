<div class="col-12 col-md-6">
    <label class="form-label" for="facility_name_{{ $facility?->id ?? 'new' }}">Nama Faskes</label>
    <input id="facility_name_{{ $facility?->id ?? 'new' }}" name="name" class="form-control" value="{{ old('name', $facility?->name) }}" required>
</div>
<div class="col-12 col-md-6">
    <label class="form-label" for="facility_code_{{ $facility?->id ?? 'new' }}">Kode Faskes</label>
    <input id="facility_code_{{ $facility?->id ?? 'new' }}" name="code" class="form-control text-uppercase" value="{{ old('code', $facility?->code) }}" placeholder="FKS-001" required>
</div>
<div class="col-12">
    <label class="form-label" for="facility_address_{{ $facility?->id ?? 'new' }}">Alamat</label>
    <textarea id="facility_address_{{ $facility?->id ?? 'new' }}" name="address" class="form-control" rows="3">{{ old('address', $facility?->address) }}</textarea>
</div>
<div class="col-12">
    <label class="form-label" for="facility_contact_{{ $facility?->id ?? 'new' }}">Kontak</label>
    <input id="facility_contact_{{ $facility?->id ?? 'new' }}" name="contact" class="form-control" value="{{ old('contact', $facility?->contact) }}" placeholder="Nomor telepon, email, atau penanggung jawab">
</div>
