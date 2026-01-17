@extends('layouts.admin')

@section('title', 'Produk Baru')

@section('content')
    <div class="card">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Tambah Produk</h2>
            <p class="text-sm text-slate-500">Isi data produk sesuai lokasi dan kategori.</p>
        </div>
        <form method="POST" action="{{ route('admin.products.store') }}" class="form-stack" enctype="multipart/form-data">
            @include('admin.products.form')
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </div>
@endsection
