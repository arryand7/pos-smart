@extends('layouts.admin')

@section('title', 'Pengaturan Branding')

@section('content')
<div class="card max-w-2xl">
    <div class="mb-4">
        <h2 class="text-xl font-semibold text-slate-800">Pengaturan Branding</h2>
        <p class="text-sm text-slate-500">Kustomisasi tampilan aplikasi SMART.</p>
    </div>

    @if(session('status'))
        <div class="alert alert-success mb-4">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="form-stack">
            <label class="form-label">
                Nama Aplikasi
                <input class="form-input" type="text" name="app_name" value="{{ $settings->app_name ?? 'SMART' }}">
            </label>

            <label class="form-label">
                Tagline
                <input class="form-input" type="text" name="tagline" value="{{ $settings->tagline ?? '' }}" placeholder="Sabira Mart Integrated Cashless System">
            </label>

            <label class="form-label">
                Logo Aplikasi
                @if($settings->app_logo)
                    <img src="{{ Storage::url($settings->app_logo) }}" alt="Logo" class="h-16 w-16 rounded-xl object-cover border border-slate-200">
                @endif
                <input class="form-input" type="file" name="app_logo" accept="image/*">
            </label>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="form-label">
                    Warna Primer
                    <div class="flex items-center gap-2">
                        <input type="color" name="primary_color" value="{{ $settings->primary_color ?? '#007A5C' }}" class="h-10 w-12 rounded-lg border border-slate-200">
                        <input class="form-input font-mono" type="text" value="{{ $settings->primary_color ?? '#007A5C' }}" readonly>
                    </div>
                </label>

                <label class="form-label">
                    Warna Aksen
                    <div class="flex items-center gap-2">
                        <input type="color" name="accent_color" value="{{ $settings->accent_color ?? '#D4AF37' }}" class="h-10 w-12 rounded-lg border border-slate-200">
                        <input class="form-input font-mono" type="text" value="{{ $settings->accent_color ?? '#D4AF37' }}" readonly>
                    </div>
                </label>
            </div>

            <label class="form-label">
                Zona Waktu
                <select class="form-select" name="timezone">
                    @foreach($timezones as $value => $label)
                        <option value="{{ $value }}" @selected(($settings->timezone ?? config('app.timezone')) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="form-help">Pengaturan ini memengaruhi timestamp laporan dan transaksi.</span>
            </label>

            <label class="form-label">
                Teks Footer
                <textarea class="form-textarea" name="footer_text" rows="2" placeholder="© 2026 Sabira Mart. All rights reserved.">{{ $settings->footer_text ?? '' }}</textarea>
            </label>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>
</div>
@endsection
