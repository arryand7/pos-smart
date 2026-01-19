@extends('layouts.admin')

@section('title', 'Edit ' . $config->name)

@section('content')
<div class="card max-w-3xl">
    <div class="mb-4">
        <h2 class="text-xl font-semibold text-slate-800">Konfigurasi {{ $config->name }}</h2>
        <p class="text-sm text-slate-500">Provider: <strong>{{ strtoupper($config->provider) }}</strong></p>
    </div>

    <form method="POST" action="{{ route('admin.settings.payments.update', $config->provider) }}">
        @csrf
        @method('PUT')

        <div class="form-stack">
            <label class="form-label">
                Nama Display
                <input class="form-input" type="text" name="name" value="{{ old('name', $config->name) }}" required>
            </label>

            <label class="form-label">
                Prioritas
                <input class="form-input w-28" type="number" name="priority" value="{{ old('priority', $config->priority) }}" min="1" required>
                <span class="form-help">Provider dengan prioritas lebih rendah akan digunakan lebih dulu.</span>
            </label>

            <label class="flex items-center gap-3 text-sm font-semibold text-slate-700">
                <input type="checkbox" name="is_active" value="1" {{ $config->is_active ? 'checked' : '' }} class="rounded text-emerald-600 focus:ring-emerald-500">
                Aktif
            </label>

            <div class="section-divider"></div>

            <h3 class="text-base font-semibold text-slate-800">Kredensial Production</h3>
            
            @if($config->provider === 'ipaymu')
                <label class="form-label">
                    Virtual Account
                    <input class="form-input" type="text" name="config[credentials][virtual_account]" value="{{ old('config.credentials.virtual_account', $config->config['credentials']['virtual_account'] ?? ($config->config['virtual_account'] ?? '')) }}">
                </label>
                <label class="form-label">
                    API Key
                    <input class="form-input" type="password" name="config[credentials][api_key]" value="{{ old('config.credentials.api_key', $config->config['credentials']['api_key'] ?? ($config->config['api_key'] ?? '')) }}">
                </label>
                <label class="form-label">
                    Private Key
                    <input class="form-input" type="password" name="config[credentials][private_key]" value="{{ old('config.credentials.private_key', $config->config['credentials']['private_key'] ?? ($config->config['private_key'] ?? '')) }}">
                </label>
                <label class="form-label">
                    Merchant Code
                    <input class="form-input" type="text" name="config[credentials][merchant_code]" value="{{ old('config.credentials.merchant_code', $config->config['credentials']['merchant_code'] ?? ($config->config['merchant_code'] ?? '')) }}">
                </label>
            @elseif($config->provider === 'midtrans')
                <label class="form-label">
                    Server Key
                    <input class="form-input" type="password" name="config[credentials][server_key]" value="{{ old('config.credentials.server_key', $config->config['credentials']['server_key'] ?? ($config->config['server_key'] ?? '')) }}">
                </label>
                <label class="form-label">
                    Client Key
                    <input class="form-input" type="text" name="config[credentials][client_key]" value="{{ old('config.credentials.client_key', $config->config['credentials']['client_key'] ?? ($config->config['client_key'] ?? '')) }}">
                </label>
                <label class="form-label">
                    Merchant ID
                    <input class="form-input" type="text" name="config[credentials][merchant_id]" value="{{ old('config.credentials.merchant_id', $config->config['credentials']['merchant_id'] ?? ($config->config['merchant_id'] ?? '')) }}">
                </label>
                <label class="form-label">
                    Mode
                    <select class="form-select w-40" name="config[mode]">
                        <option value="sandbox" @selected(old('config.mode', $config->config['mode'] ?? 'sandbox') === 'sandbox')>Sandbox</option>
                        <option value="production" @selected(old('config.mode', $config->config['mode'] ?? 'sandbox') === 'production')>Production</option>
                    </select>
                    <span class="form-help">Sesuaikan dengan environment Midtrans Anda.</span>
                </label>
            @elseif($config->provider === 'doku')
                <label class="form-label">
                    Client ID
                    <input class="form-input" type="text" name="config[credentials][client_id]" value="{{ old('config.credentials.client_id', $config->config['credentials']['client_id'] ?? ($config->config['client_id'] ?? '')) }}">
                </label>
                <label class="form-label">
                    Secret Key
                    <input class="form-input" type="password" name="config[credentials][secret_key]" value="{{ old('config.credentials.secret_key', $config->config['credentials']['secret_key'] ?? ($config->config['secret_key'] ?? '')) }}">
                </label>
                <label class="form-label">
                    Merchant Code
                    <input class="form-input" type="text" name="config[credentials][merchant_code]" value="{{ old('config.credentials.merchant_code', $config->config['credentials']['merchant_code'] ?? ($config->config['merchant_code'] ?? '')) }}">
                </label>
            @endif
        </div>

        @if($config->provider === 'midtrans')
            <div class="section-divider"></div>
            <h3 class="text-base font-semibold text-slate-800">URL Endpoints Midtrans</h3>
            <p class="text-sm text-slate-500 mb-3">Salin URL berikut ke dashboard Midtrans (Settings -> Configuration).</p>
            <div class="form-grid">
                <label class="form-label">Payment Notification URL
                    <input class="form-input font-mono" type="text" value="{{ url('/api/payments/webhook/midtrans') }}" readonly>
                </label>
                <label class="form-label">Recurring Notification URL
                    <input class="form-input font-mono" type="text" value="{{ url('/api/payments/webhook/midtrans') }}" readonly>
                </label>
                <label class="form-label">Pay Account Notification URL
                    <input class="form-input font-mono" type="text" value="{{ url('/api/payments/webhook/midtrans') }}" readonly>
                </label>
                <label class="form-label">Finish Redirect URL
                    <input class="form-input font-mono" type="text" value="{{ url('/payments/midtrans/redirect?status=success') }}" readonly>
                </label>
                <label class="form-label">Unfinish Redirect URL
                    <input class="form-input font-mono" type="text" value="{{ url('/payments/midtrans/redirect?status=pending') }}" readonly>
                </label>
                <label class="form-label">Error Redirect URL
                    <input class="form-input font-mono" type="text" value="{{ url('/payments/midtrans/redirect?status=failed') }}" readonly>
                </label>
            </div>
        @endif

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Simpan Konfigurasi</button>
            <a href="{{ route('admin.settings.payments') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection
