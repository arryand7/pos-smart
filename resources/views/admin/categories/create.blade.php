@extends('layouts.admin')

@section('title', 'Kategori Baru')

@section('content')
    <div class="card">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Tambah Kategori</h2>
            <p class="text-sm text-slate-500">Atur kategori produk untuk pengelompokan inventaris.</p>
        </div>
        <form method="POST" action="{{ route('admin.categories.store') }}" class="form-stack">
            @include('admin.categories.form')
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </div>
@endsection
