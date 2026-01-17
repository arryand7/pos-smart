@csrf
<div class="form-grid">
    <label class="form-label">Nama Kategori
        <input class="form-input" type="text" name="name" value="{{ old('name', $category->name ?? '') }}" required>
    </label>
    <label class="form-label">Slug
        <input class="form-input" type="text" name="slug" value="{{ old('slug', $category->slug ?? '') }}" placeholder="Opsional">
    </label>
    <label class="form-label">Status Blokir
        <select class="form-select" name="is_restricted">
            <option value="0" @selected(! old('is_restricted', $category->is_restricted ?? false))>Izinkan Semua</option>
            <option value="1" @selected(old('is_restricted', $category->is_restricted ?? false))>Terbatas</option>
        </select>
    </label>
</div>
<div class="mt-4">
    <label class="form-label">Deskripsi
        <textarea class="form-textarea" name="description" rows="3">{{ old('description', $category->description ?? '') }}</textarea>
    </label>
</div>
