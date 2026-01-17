@extends('layouts.admin')

@section('title', 'Edit Lokasi')

@section('content')
    <div class="card">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Edit Lokasi</h2>
            <p class="text-sm text-slate-500">Perbarui informasi lokasi sesuai kebutuhan.</p>
        </div>
        <form method="POST" action="{{ route('admin.locations.update', $location) }}" class="form-stack">
            @method('PUT')
            @include('admin.locations.form')
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">Perbarui</button>
                <a href="{{ route('admin.locations.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </div>
@endsection
