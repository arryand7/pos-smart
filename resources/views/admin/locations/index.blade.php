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

        <div class="table-scroll">
            <table class="table">
                <thead>
                <tr>
                    <th>Lokasi</th>
                    <th>Kode</th>
                    <th>Tipe</th>
                    <th>Penanggung Jawab</th>
                    <th>Status</th>
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
