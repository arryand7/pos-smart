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

        <form method="GET" class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div class="flex flex-wrap items-end gap-3">
                <label class="text-xs font-semibold text-slate-500">
                    Cari
                    <input type="text" name="search" value="{{ request('search') }}" class="form-input mt-1 w-64" placeholder="Nama, slug...">
                </label>
                <label class="text-xs font-semibold text-slate-500">
                    Per halaman
                    <select name="per_page" class="form-select mt-1">
                        @foreach([10,15,25,50,100] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 15) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </label>
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="direction" value="{{ request('direction') }}">
                <button type="submit" class="btn btn-secondary">Terapkan</button>
            </div>
            <div class="flex items-center gap-2">
                @php $exportQuery = request()->except(['export', 'page']); @endphp
                <a class="btn btn-outline btn-sm" href="{{ request()->url().'?' . http_build_query(array_merge($exportQuery, ['export' => 'excel'])) }}">Excel</a>
                <a class="btn btn-outline btn-sm" href="{{ request()->url().'?' . http_build_query(array_merge($exportQuery, ['export' => 'csv'])) }}">CSV</a>
                <a class="btn btn-outline btn-sm" href="{{ request()->url().'?' . http_build_query(array_merge($exportQuery, ['export' => 'pdf'])) }}">PDF</a>
            </div>
        </form>

        <div class="table-scroll">
            <table class="table">
                <thead>
                <tr>
                    <x-sortable-th field="name" label="Kategori" />
                    <x-sortable-th field="slug" label="Slug" />
                    <x-sortable-th field="is_restricted" label="Status" />
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
