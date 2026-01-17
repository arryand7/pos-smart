@extends('layouts.admin')

@section('title', 'Pengaturan SSO')

@section('content')
    <div class="card">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Pengaturan SSO</h2>
            <p class="text-sm text-slate-500">Kelola koneksi OAuth2 ke Sabira Connect untuk login terpadu.</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger mb-4">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.sso.update') }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <label for="sso_base_url" class="form-label">
                    Base URL SSO
                    <input class="form-input" type="url" id="sso_base_url" name="sso_base_url" value="{{ old('sso_base_url', $setting->sso_base_url) }}" placeholder="https://gate.sabira-iibs.id">
                </label>
                <label for="sso_redirect_uri" class="form-label">
                    Redirect URI
                    <input class="form-input" type="url" id="sso_redirect_uri" name="sso_redirect_uri" value="{{ old('sso_redirect_uri', $setting->sso_redirect_uri) }}" placeholder="{{ route('sso.callback') }}">
                    <span class="form-help">Gunakan endpoint callback aplikasi ini (contoh: {{ route('sso.callback') }}).</span>
                </label>
            </div>

            <div class="form-grid mt-4">
                <label for="sso_client_id" class="form-label">
                    Client ID
                    <input class="form-input" type="text" id="sso_client_id" name="sso_client_id" value="{{ old('sso_client_id', $setting->sso_client_id) }}">
                </label>
                <label for="sso_client_secret" class="form-label">
                    Client Secret
                    <input class="form-input" type="password" id="sso_client_secret" name="sso_client_secret" placeholder="********">
                    <span class="form-help">Kosongkan jika tidak ingin mengubah secret.</span>
                </label>
            </div>

            <div class="mt-4">
                <label for="sso_scopes" class="form-label">
                    Scopes
                    <input class="form-input" type="text" id="sso_scopes" name="sso_scopes" value="{{ old('sso_scopes', $setting->sso_scopes) }}" placeholder="openid profile email roles">
                </label>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <button type="reset" class="btn btn-outline">Reset</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
@endsection
