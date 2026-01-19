@extends('layouts.admin')

@section('title', 'Lokasi Usaha')

@section('content')
    <div class="card">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">Lokasi Usaha</h2>
                <p class="text-sm text-slate-500">Kelola titik operasional (kantin, koperasi, dll).</p>
            </div>
            <a href="{{ route('admin.locations.create') }}" class="btn btn-primary">+ Lokasi Baru</a>
        </div>

        <form method="GET" class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div class="flex flex-wrap items-end gap-3">
                <label class="text-xs font-semibold text-slate-500">
                    Cari
                    <input type="text" name="search" value="{{ request('search') }}" class="form-input mt-1 w-64" placeholder="Nama, kode, PIC...">
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
                    <x-sortable-th field="name" label="Lokasi" />
                    <x-sortable-th field="code" label="Kode" />
                    <x-sortable-th field="type" label="Tipe" />
                    <x-sortable-th field="manager_name" label="Penanggung Jawab" />
                    <x-sortable-th field="is_active" label="Status" />
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($locations as $location)
                    <tr>
                        <td>
                            <strong>{{ $location->name }}</strong><br>
                            <small class="text-slate-500">{{ $location->address }}</small>
                        </td>
                        <td>{{ $location->code }}</td>
                        <td>{{ $location->type ?? '-' }}</td>
                        <td>{{ $location->manager_name ?? '-' }}</td>
                        <td>
                            <span class="status {{ $location->is_active ? 'active' : 'inactive' }}">
                                {{ $location->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="text-right space-x-2">
                            <a href="{{ route('admin.locations.edit', $location) }}" class="btn btn-outline btn-sm">Ubah</a>
                            <form action="{{ route('admin.locations.destroy', $location) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus lokasi ini?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-ghost btn-sm" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-6 text-slate-400">Belum ada lokasi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $locations->links() }}
        </div>
    </div>
@endsection
