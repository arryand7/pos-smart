@extends('layouts.admin')

@section('title', 'Kategori Produk')

@section('content')
    <div class="card">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">Kategori Produk</h2>
                <p class="text-sm text-slate-500">Atur grup produk dan blokir kategori untuk santri.</p>
            </div>
            <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">+ Kategori Baru</a>
        </div>

        <div class="table-scroll">
            <table class="table">
                <thead>
                <tr>
                    <th>Kategori</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>
                            <strong>{{ $category->name }}</strong><br>
                            <small class="text-slate-500">{{ $category->description }}</small>
                        </td>
                        <td>{{ $category->slug }}</td>
                        <td>
                            <span class="status {{ $category->is_restricted ? 'inactive' : 'active' }}">
                                {{ $category->is_restricted ? 'Terbatas' : 'Bebas' }}
                            </span>
                        </td>
                        <td class="text-right space-x-2">
                            <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-outline btn-sm">Ubah</a>
                            <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus kategori ini?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-ghost btn-sm" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center py-6 text-slate-400">Belum ada kategori.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $categories->links() }}
        </div>
    </div>
@endsection
