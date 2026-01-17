@extends('layouts.app')

@section('title', 'Pengaturan Profil')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-10">
    <div class="card">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-800">Pengaturan Profil</h1>
                <p class="text-sm text-slate-500">Perbarui identitas akun dan keamanan login.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $homeUrl }}" class="btn btn-outline">Kembali ke {{ $homeLabel }}</a>
                <form method="POST" action="{{ route('auth.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost">Keluar</button>
                </form>
            </div>
        </div>

        @if(session('status'))
            <div class="alert alert-success mt-4">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger mt-4">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('profile.update') }}" class="form-stack mt-6">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <label class="form-label">
                    Nama Lengkap
                    <input class="form-input" type="text" name="name" value="{{ old('name', $user?->name) }}" required>
                </label>
                <label class="form-label">
                    Nomor HP
                    <input class="form-input" type="text" name="phone" value="{{ old('phone', $user?->phone) }}" placeholder="08xxxxxxxxxx">
                </label>
            </div>

            <div class="form-grid">
                <label class="form-label">
                    Email
                    <input class="form-input" type="text" value="{{ $user?->email }}" disabled>
                </label>
                <label class="form-label">
                    Role
                    <input class="form-input" type="text" value="{{ $user?->role?->value ?? $user?->role }}" disabled>
                </label>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-slate-800">Ubah Password</h2>
                <p class="text-sm text-slate-500">Isi jika ingin mengganti password.</p>
            </div>

            <div class="form-grid">
                <label class="form-label">
                    Password Saat Ini
                    <input class="form-input" type="password" name="current_password" placeholder="Password saat ini">
                </label>
                <label class="form-label">
                    Password Baru
                    <input class="form-input" type="password" name="password" placeholder="Minimal 6 karakter">
                </label>
                <label class="form-label">
                    Konfirmasi Password
                    <input class="form-input" type="password" name="password_confirmation" placeholder="Ulangi password baru">
                </label>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">Simpan Profil</button>
            </div>
        </form>
    </div>
</div>
@endsection
