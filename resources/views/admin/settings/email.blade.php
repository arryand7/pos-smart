@extends('layouts.admin')

@section('title', 'Pengaturan Email')

@section('content')
<div class="card max-w-2xl">
    <div class="mb-4">
        <h2 class="text-xl font-semibold text-slate-800">Pengaturan Email (SMTP)</h2>
        <p class="text-sm text-slate-500">Konfigurasi server email untuk notifikasi sistem.</p>
    </div>

    @if(session('status'))
        <div class="alert alert-success mb-4">
            {{ session('status') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger mb-4">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.email.update') }}">
        @csrf
        @method('PUT')

        <div class="form-stack">
            <label class="form-label">
                Mailer
                <select class="form-select" name="mail_mailer">
                    <option value="">Pilih Mailer</option>
                    <option value="smtp" {{ ($settings->mail_mailer ?? '') === 'smtp' ? 'selected' : '' }}>SMTP</option>
                    <option value="sendmail" {{ ($settings->mail_mailer ?? '') === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                    <option value="mailgun" {{ ($settings->mail_mailer ?? '') === 'mailgun' ? 'selected' : '' }}>Mailgun</option>
                </select>
            </label>

            <div class="grid gap-4 md:grid-cols-[1fr_120px]">
                <label class="form-label">
                    SMTP Host
                    <input class="form-input" type="text" name="mail_host" value="{{ $settings->mail_host ?? '' }}" placeholder="smtp.example.com">
                </label>
                <label class="form-label">
                    Port
                    <input class="form-input" type="number" name="mail_port" value="{{ $settings->mail_port ?? 587 }}">
                </label>
            </div>

            <label class="form-label">
                Username
                <input class="form-input" type="text" name="mail_username" value="{{ $settings->mail_username ?? '' }}">
            </label>

            <label class="form-label">
                Password
                <input class="form-input" type="password" name="mail_password" value="{{ $settings->mail_password ?? '' }}">
            </label>

            <label class="form-label">
                Encryption
                <select class="form-select" name="mail_encryption">
                    <option value="">None</option>
                    <option value="tls" {{ ($settings->mail_encryption ?? '') === 'tls' ? 'selected' : '' }}>TLS</option>
                    <option value="ssl" {{ ($settings->mail_encryption ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                </select>
            </label>

            <div class="section-divider"></div>

            <label class="form-label">
                From Address
                <input class="form-input" type="email" name="mail_from_address" value="{{ $settings->mail_from_address ?? '' }}" placeholder="noreply@example.com">
            </label>

            <label class="form-label">
                From Name
                <input class="form-input" type="text" name="mail_from_name" value="{{ $settings->mail_from_name ?? 'SMART' }}">
            </label>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>

    <div class="section-divider"></div>

    <h3 class="text-lg font-semibold text-slate-800 mb-3">Test Email</h3>
    <form method="POST" action="{{ route('admin.settings.email.test') }}" class="flex flex-wrap gap-3 items-end">
        @csrf
        <label class="form-label flex-1">
            Kirim email percobaan ke:
            <input class="form-input" type="email" name="test_email" required placeholder="your@email.com">
        </label>
        <button type="submit" class="btn btn-outline">Kirim Test</button>
    </form>
</div>
@endsection
