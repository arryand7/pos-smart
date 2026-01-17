@extends('layouts.admin')

@section('title', 'Edit Produk')

@section('content')
    <div class="card">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Edit Produk</h2>
            <p class="text-sm text-slate-500">Perbarui informasi produk sesuai kebutuhan.</p>
        </div>
        <form method="POST" action="{{ route('admin.products.update', $product) }}" class="form-stack" enctype="multipart/form-data">
            @method('PUT')
            @include('admin.products.form')
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">Perbarui</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </div>
@endsection
