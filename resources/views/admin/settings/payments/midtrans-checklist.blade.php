@extends('layouts.admin')

@section('title', 'Midtrans Checklist')

@section('content')
<div class="card max-w-3xl">
    <div class="mb-4">
        <h2 class="text-xl font-semibold text-slate-800">Midtrans Checklist</h2>
        <p class="text-sm text-slate-500">Salin pengaturan berikut ke dashboard Midtrans sebelum mengaktifkan.</p>
    </div>

    <div class="form-stack">
        <div class="form-grid">
            <label class="form-label">Mode
                <input class="form-input" type="text" value="{{ strtoupper($data['mode'] ?? 'SANDBOX') }}" readonly>
            </label>
            <label class="form-label">Merchant ID
                <input class="form-input" type="text" value="{{ $data['merchant_id'] ?: '-' }}" readonly>
            </label>
            <label class="form-label">Client Key
                <input class="form-input" type="text" value="{{ $data['client_key'] ?: '-' }}" readonly>
            </label>
            <label class="form-label">Server Key
                <input class="form-input" type="text" value="{{ $data['server_key'] ?: '-' }}" readonly>
            </label>
        </div>

        <div class="section-divider"></div>

        <h3 class="text-base font-semibold text-slate-800">URL Endpoints</h3>
        <p class="text-sm text-slate-500">Gunakan URL berikut di dashboard Midtrans (Settings -> Configuration).</p>

        <div class="form-grid">
            <label class="form-label">Payment Notification URL
                <input class="form-input font-mono" type="text" value="{{ $urls['payment_notification'] }}" readonly>
            </label>
            <label class="form-label">Recurring Notification URL
                <input class="form-input font-mono" type="text" value="{{ $urls['recurring_notification'] }}" readonly>
            </label>
            <label class="form-label">Pay Account Notification URL
                <input class="form-input font-mono" type="text" value="{{ $urls['pay_account_notification'] }}" readonly>
            </label>
            <label class="form-label">Finish Redirect URL
                <input class="form-input font-mono" type="text" value="{{ $urls['finish_redirect'] }}" readonly>
            </label>
            <label class="form-label">Unfinish Redirect URL
                <input class="form-input font-mono" type="text" value="{{ $urls['unfinish_redirect'] }}" readonly>
            </label>
            <label class="form-label">Error Redirect URL
                <input class="form-input font-mono" type="text" value="{{ $urls['error_redirect'] }}" readonly>
            </label>
        </div>

        <div class="section-divider"></div>

        <div class="soft-panel p-4">
            <h4 class="text-sm font-semibold text-slate-700 mb-1">Checklist Aktivasi</h4>
            <ul class="text-sm text-slate-500 space-y-1">
                <li>1. Pastikan mode (Sandbox/Production) sesuai di dashboard Midtrans.</li>
                <li>2. Simpan Merchant ID, Client Key, dan Server Key ke halaman konfigurasi payment gateway.</li>
                <li>3. Isi semua URL endpoint di atas pada dashboard Midtrans.</li>
                <li>4. Aktifkan Midtrans sebagai provider dengan prioritas tertinggi jika ingin dipakai default.</li>
            </ul>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.settings.payments') }}" class="btn btn-outline">Kembali</a>
        <a href="{{ route('admin.settings.payments.edit', 'midtrans') }}" class="btn btn-primary">Konfigurasi Midtrans</a>
    </div>
</div>
@endsection
