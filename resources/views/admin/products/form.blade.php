@csrf
<div class="form-grid">
    <label class="form-label">Nama Produk
        <input class="form-input" type="text" name="name" value="{{ old('name', $product->name ?? '') }}" required>
    </label>
    <label class="form-label">SKU
        <input class="form-input" type="text" name="sku" value="{{ old('sku', $product->sku ?? 'PRD'.now()->timestamp) }}" required>
    </label>
    <label class="form-label">Barcode
        <div class="flex items-center gap-2">
            <input id="barcode-input" class="form-input flex-1" type="text" name="barcode" value="{{ old('barcode', $product->barcode ?? null) }}">
            <button type="button" class="btn btn-outline btn-sm" id="scan-barcode-btn">Scan</button>
        </div>
        <span class="form-help">Gunakan kamera untuk memindai barcode produk.</span>
    </label>
    @php
        $productPhotoUrl = ($product ?? null)?->photo_url;
    @endphp
    <label class="form-label">Foto Produk
        <input id="product-photo-input" class="form-input" type="file" name="photo" accept="image/*">
        <span class="form-help">Format JPG/PNG/WebP, maksimal 2MB.</span>
        <div class="product-photo">
            <div class="product-photo-preview" id="product-photo-preview">
                @if(!empty($productPhotoUrl))
                    <img src="{{ $productPhotoUrl }}" alt="{{ $product->name ?? 'Foto produk' }}">
                @else
                    <span>Belum ada foto</span>
                @endif
            </div>
            <div class="text-xs text-slate-500">Foto akan tampil di katalog dan POS.</div>
        </div>
    </label>
    <label class="form-label">Lokasi
        <select class="form-select" name="location_id" required>
            @foreach($locations as $location)
                <option value="{{ $location->id }}" @selected(old('location_id', $product->location_id ?? null) == $location->id)>{{ $location->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="form-label">Kategori
        <select class="form-select" name="category_id">
            <option value="">Tanpa Kategori</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id ?? null) == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="form-label">Unit
        <input class="form-input" type="text" name="unit" value="{{ old('unit', $product->unit ?? 'pcs') }}" required>
    </label>
    <label class="form-label">Harga Modal
        <input class="form-input" type="number" name="cost_price" min="0" step="100" value="{{ old('cost_price', $product->cost_price ?? 0) }}" required>
    </label>
    <label class="form-label">Harga Jual
        <input class="form-input" type="number" name="sale_price" min="0" step="100" value="{{ old('sale_price', $product->sale_price ?? 0) }}" required>
    </label>
    <label class="form-label">Stok
        <input class="form-input" type="number" name="stock" min="0" value="{{ old('stock', $product->stock ?? 0) }}" required>
    </label>
    <label class="form-label">Peringatan Stok
        <input class="form-input" type="number" name="stock_alert" min="0" value="{{ old('stock_alert', $product->stock_alert ?? 10) }}">
    </label>
    <label class="form-label">Status
        <select class="form-select" name="is_active">
            <option value="1" @selected(old('is_active', $product->is_active ?? true))>Aktif</option>
            <option value="0" @selected(old('is_active', $product->is_active ?? true) === false)>Nonaktif</option>
        </select>
    </label>
</div>
<div class="mt-4">
    <label class="form-label">Deskripsi
        <textarea class="form-textarea" name="description" rows="3">{{ old('description', $product->description ?? '') }}</textarea>
    </label>
</div>

<div class="barcode-modal" id="barcode-scan-modal" hidden>
    <div class="barcode-card">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-base font-semibold text-slate-800">Scan Barcode</h3>
            <button type="button" class="btn btn-ghost btn-sm" id="barcode-scan-close">Tutup</button>
        </div>
        <video id="barcode-video" class="barcode-video" autoplay playsinline muted></video>
        <p class="barcode-hint" id="barcode-hint">Arahkan kamera ke barcode produk.</p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const scanButton = document.getElementById('scan-barcode-btn');
        const modal = document.getElementById('barcode-scan-modal');
        const closeButton = document.getElementById('barcode-scan-close');
        const video = document.getElementById('barcode-video');
        const hint = document.getElementById('barcode-hint');
        const input = document.getElementById('barcode-input');
        const photoInput = document.getElementById('product-photo-input');
        const photoPreview = document.getElementById('product-photo-preview');

        if (!scanButton || !modal || !video || !input) {
            return;
        }

        let stream = null;
        let detector = null;
        let rafId = null;

        const stopScan = () => {
            if (rafId) {
                cancelAnimationFrame(rafId);
                rafId = null;
            }
            if (stream) {
                stream.getTracks().forEach((track) => track.stop());
                stream = null;
            }
            video.srcObject = null;
            modal.hidden = true;
        };

        const scanLoop = async () => {
            if (!detector || video.readyState < 2) {
                rafId = requestAnimationFrame(scanLoop);
                return;
            }

            try {
                const barcodes = await detector.detect(video);
                if (barcodes && barcodes.length > 0) {
                    const value = barcodes[0].rawValue || barcodes[0].rawValue?.toString?.();
                    if (value) {
                        input.value = value;
                        stopScan();
                        return;
                    }
                }
            } catch (error) {
                // ignore detection errors and continue scanning
            }

            rafId = requestAnimationFrame(scanLoop);
        };

        const startScan = async () => {
            if (!('BarcodeDetector' in window)) {
                alert('Browser tidak mendukung pemindaian barcode. Silakan isi manual.');
                return;
            }

            try {
                detector = new BarcodeDetector({
                    formats: ['code_128', 'ean_13', 'ean_8', 'code_39', 'upc_a', 'upc_e', 'qr_code']
                });
            } catch (error) {
                detector = new BarcodeDetector();
            }

            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } }
                });
                video.srcObject = stream;
                await video.play();
                modal.hidden = false;
                hint.textContent = 'Arahkan kamera ke barcode produk.';
                rafId = requestAnimationFrame(scanLoop);
            } catch (error) {
                alert('Tidak bisa mengakses kamera. Pastikan izin kamera diaktifkan.');
            }
        };

        scanButton.addEventListener('click', startScan);
        closeButton?.addEventListener('click', stopScan);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                stopScan();
            }
        });

        if (photoInput && photoPreview) {
            photoInput.addEventListener('change', () => {
                const file = photoInput.files?.[0];
                if (!file) {
                    return;
                }
                const url = URL.createObjectURL(file);
                photoPreview.innerHTML = `<img src=\"${url}\" alt=\"Preview foto produk\">`;
            });
        }
    });
</script>
