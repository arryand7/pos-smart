@extends('layouts.admin')

@section('title', 'Lokasi Baru')

@section('content')
    <div class="card">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Tambah Lokasi</h2>
            <p class="text-sm text-slate-500">Lengkapi data lokasi untuk pencatatan inventaris.</p>
        </div>
        <form method="POST" action="{{ route('admin.locations.store') }}" class="form-stack">
            @include('admin.locations.form')
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('admin.locations.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </div>
@endsection
