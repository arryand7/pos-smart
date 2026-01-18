@extends('layouts.admin')

@section('title', 'Pengaturan Santri')

@section('content')
    <div class="card">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Pengaturan Santri</h2>
            <p class="text-sm text-slate-500">{{ $santri->name }} • NIS: {{ $santri->nis }}</p>
        </div>
        <form method="POST" action="{{ route('admin.santri.update', $santri) }}" class="form-stack">
            @method('PUT')
            @csrf
            <div class="form-grid">
                <label class="form-label">QR Code Santri
                    <div class="flex items-center gap-2">
                        <input id="santri-qr-input" class="form-input flex-1" type="text" name="qr_code" value="{{ old('qr_code', $santri->qr_code) }}" placeholder="Scan / masukkan QR code">
                        <button type="button" class="btn btn-outline btn-sm" id="scan-santri-qr-btn">Scan</button>
                    </div>
                    <span class="form-help">Gunakan QR pada kartu santri untuk memudahkan scan POS.</span>
                </label>
                <label class="form-label">Saldo Saat Ini
                    <input class="form-input" type="text" value="Rp{{ number_format($santri->wallet_balance, 0, ',', '.') }}" disabled>
                </label>
                <label class="form-label">Limit Harian
                    <input class="form-input" type="number" name="daily_limit" min="0" value="{{ old('daily_limit', $santri->daily_limit) }}">
                </label>
                <label class="form-label">Limit Mingguan
                    <input class="form-input" type="number" name="weekly_limit" min="0" value="{{ old('weekly_limit', $santri->weekly_limit) }}">
                </label>
                <label class="form-label">Limit Bulanan
                    <input class="form-input" type="number" name="monthly_limit" min="0" value="{{ old('monthly_limit', $santri->monthly_limit) }}">
                </label>
                <label class="form-label">Status Dompet
                    <select class="form-select" name="is_wallet_locked">
                        <option value="0" @selected(! $santri->is_wallet_locked)>Aktif</option>
                        <option value="1" @selected($santri->is_wallet_locked)>Diblokir</option>
                    </select>
                </label>
            </div>
            <div>
                <label class="form-label">Catatan
                    <textarea class="form-textarea" name="notes" rows="3">{{ old('notes', $santri->notes) }}</textarea>
                </label>
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('admin.santri.index') }}" class="btn btn-outline">Kembali</a>
            </div>
        </form>
    </div>

    <div class="barcode-modal" id="santri-qr-modal" hidden>
        <div class="barcode-card">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-base font-semibold text-slate-800">Scan QR Santri</h3>
                <button type="button" class="btn btn-ghost btn-sm" id="santri-qr-close">Tutup</button>
            </div>
            <video id="santri-qr-video" class="barcode-video" autoplay playsinline muted></video>
            <p class="barcode-hint" id="santri-qr-hint">Arahkan kamera ke QR code kartu santri.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const scanButton = document.getElementById('scan-santri-qr-btn');
            const modal = document.getElementById('santri-qr-modal');
            const closeButton = document.getElementById('santri-qr-close');
            const video = document.getElementById('santri-qr-video');
            const input = document.getElementById('santri-qr-input');

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
                    const codes = await detector.detect(video);
                    if (codes && codes.length > 0) {
                        const value = codes[0].rawValue;
                        if (value) {
                            input.value = value;
                            stopScan();
                            return;
                        }
                    }
                } catch (error) {
                    // ignore detection errors and continue
                }

                rafId = requestAnimationFrame(scanLoop);
            };

            const startScan = async () => {
                if (!('BarcodeDetector' in window)) {
                    alert('Browser tidak mendukung pemindaian QR. Silakan isi manual.');
                    return;
                }

                try {
                    detector = new BarcodeDetector({ formats: ['qr_code'] });
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
        });
    </script>
@endsection
