@extends('layouts.admin')

@section('title', 'Edit Wali')

@section('content')
<div class="card max-w-2xl">
    <div class="mb-4">
        <h2 class="text-xl font-semibold text-slate-800">Edit Wali: {{ $wali->name }}</h2>
        <p class="text-sm text-slate-500">Kelola informasi wali santri dan akses portal.</p>
    </div>

    <form method="POST" action="{{ route('admin.wali.update', $wali) }}">
        @csrf
        @method('PUT')

        <div class="form-stack">
            <label class="form-label">
                Nama Lengkap
                <input class="form-input" type="text" name="name" value="{{ old('name', $wali->name) }}" required>
                @error('name') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <label class="form-label">
                Email
                <input class="form-input" type="email" name="email" value="{{ old('email', $wali->email) }}" required>
                @error('email') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <label class="form-label">
                No. Telepon
                <input class="form-input" type="text" name="phone" value="{{ old('phone', $wali->phone) }}" required>
                @error('phone') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <label class="form-label">
                Alamat
                <textarea class="form-textarea" name="address" rows="2">{{ old('address', $wali->address) }}</textarea>
                @error('address') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <label class="form-label">
                Password Baru <span class="form-help">(kosongkan jika tidak diubah)</span>
                <input class="form-input" type="password" name="password">
                @error('password') <small class="text-xs text-rose-600">{{ $message }}</small> @enderror
            </label>

            <label class="form-label">
                Konfirmasi Password
                <input class="form-input" type="password" name="password_confirmation">
            </label>
        </div>

        @if($wali->santris->count() > 0)
            <div class="mt-6 soft-panel p-4">
                <h4 class="text-sm font-semibold text-slate-700 mb-2">Santri dalam asuhan</h4>
                <ul class="list-disc pl-5 text-sm text-slate-600 space-y-1">
                    @foreach($wali->santris as $santri)
                        <li>{{ $santri->name }} ({{ $santri->nis }})</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-wrap gap-3 mt-6">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            <a href="{{ route('admin.wali.index') }}" class="btn btn-outline">Batal</a>
        </div>
    </form>
</div>
@endsection
