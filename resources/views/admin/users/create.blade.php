@extends('layouts.admin')

@section('title', 'Tambah Pengguna')

@section('content')
<div class="card max-w-2xl">
    <div class="mb-4">
        <h2 class="text-xl font-semibold text-slate-800">Tambah Pengguna Baru</h2>
        <p class="text-sm text-slate-500">Buat akun baru untuk akses internal sesuai kebutuhan.</p>
    </div>

    <form method="POST" action="{{ route('admin.users.store') }}">
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
                <input class="form-input" type="text" name="phone" value="{{ old('phone') }}">
                @error('phone') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <label class="form-label">
                Role
                <select class="form-select" name="role" required>
                    <option value="">Pilih Role</option>
                    @foreach($roles as $value => $label)
                        <option value="{{ $value }}" {{ old('role') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <div class="form-label">
                Role Tambahan
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach($roles as $value => $label)
                        <label class="flex items-center gap-2 text-sm font-medium text-slate-600">
                            <input type="checkbox" name="roles[]" value="{{ $value }}" class="rounded text-emerald-600 focus:ring-emerald-500"
                                @checked(in_array($value, old('roles', [])))>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <span class="form-help">Pengguna dapat memiliki lebih dari satu role untuk akses lintas portal.</span>
                @error('roles') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </div>

            <label class="form-label">
                Password
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
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection
