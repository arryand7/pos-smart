@extends('layouts.admin')

@section('title', 'Tambah Wali')

@section('content')
<div class="card max-w-2xl">
    <div class="mb-4">
        <h2 class="text-xl font-semibold text-slate-800">Tambah Wali Baru</h2>
        <p class="text-sm text-slate-500">Buat akun wali untuk akses portal monitoring santri.</p>
    </div>

    <form method="POST" action="{{ route('admin.wali.store') }}">
        @csrf

        <div class="form-stack">
            <label class="form-label">
                Nama Lengkap
                <input class="form-input" type="text" name="name" value="{{ old('name') }}" required>
                @error('name') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <label class="form-label">
                Email
                <input class="form-input" type="email" name="email" value="{{ old('email') }}" required>
                @error('email') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <label class="form-label">
                No. Telepon
                <input class="form-input" type="text" name="phone" value="{{ old('phone') }}" required>
                @error('phone') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <label class="form-label">
                Alamat <span class="form-help">(opsional)</span>
                <textarea class="form-textarea" name="address" rows="2">{{ old('address') }}</textarea>
                @error('address') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <label class="form-label">
                Password (untuk login portal)
                <input class="form-input" type="password" name="password" required>
                @error('password') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <label class="form-label">
                Konfirmasi Password
                <input class="form-input" type="password" name="password_confirmation" required>
            </label>
        </div>

        <div class="flex flex-wrap gap-3 mt-6">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('admin.wali.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection
