@csrf
<div class="form-grid">
    <label class="form-label">Nama Lokasi
        <input class="form-input" type="text" name="name" value="{{ old('name', $location->name ?? '') }}" required>
    </label>
    <label class="form-label">Kode
        <input class="form-input" type="text" name="code" value="{{ old('code', $location->code ?? '') }}" required>
    </label>
    <label class="form-label">Tipe
        <input class="form-input" type="text" name="type" value="{{ old('type', $location->type ?? '') }}">
    </label>
    <label class="form-label">Penanggung Jawab
        <input class="form-input" type="text" name="manager_name" value="{{ old('manager_name', $location->manager_name ?? '') }}">
    </label>
    <label class="form-label">Telepon
        <input class="form-input" type="text" name="phone" value="{{ old('phone', $location->phone ?? '') }}">
    </label>
    <label class="form-label">Status
        <select class="form-select" name="is_active">
            <option value="1" @selected(old('is_active', $location->is_active ?? true))>Aktif</option>
            <option value="0" @selected(old('is_active', $location->is_active ?? true) === false)>Nonaktif</option>
        </select>
    </label>
</div>
<div class="mt-4">
    <label class="form-label">Alamat
        <textarea class="form-textarea" name="address" rows="3">{{ old('address', $location->address ?? '') }}</textarea>
    </label>
</div>
