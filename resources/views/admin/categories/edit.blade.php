@extends('layouts.admin')

@section('title', 'Edit Kategori')

@section('content')
    <div class="card">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Edit Kategori</h2>
            <p class="text-sm text-slate-500">Perbarui detail kategori sesuai kebutuhan.</p>
        </div>
        <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="form-stack">
            @method('PUT')
            @include('admin.categories.form')
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">Perbarui</button>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
    </div>
@endsection
