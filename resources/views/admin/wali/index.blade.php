@extends('layouts.admin')

@section('title', 'Data Wali Santri')
@section('subtitle', 'Manajemen akun orang tua/wali santri.')

@section('actions')
    @php
        $isSuperAdmin = auth()->check() && auth()->user()->hasRole('super_admin');
    @endphp
    @if($isSuperAdmin)
        <a href="{{ route('admin.wali.create') }}" class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Wali
        </a>
    @endif
@endsection

@section('content')
<div class="admin-card">
    @php
        $isSuperAdmin = auth()->check() && auth()->user()->hasRole('super_admin');
    @endphp
    <form method="GET" class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <label class="text-xs font-semibold text-slate-500">
                Cari
                <input type="text" name="search" value="{{ request('search') }}" class="form-input mt-1 w-64" placeholder="Nama, email, telepon...">
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
        <table class="datatable w-full">
        <thead>
            <tr>
                <x-sortable-th field="name" label="Nama Wali" />
                <x-sortable-th field="phone" label="Kontak" />
                <x-sortable-th field="address" label="Alamat" />
                <x-sortable-th field="santris_count" label="Jumlah Santri" />
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($walis as $wali)
            <tr>
                <td>
                    <div class="font-medium text-slate-800">{{ $wali->name }}</div>
                </td>
                <td class="text-sm">
                    <div class="text-slate-600">{{ $wali->phone }}</div>
                    <div class="text-xs text-slate-400">{{ $wali->email }}</div>
                </td>
                <td class="text-sm text-slate-500 max-w-[200px] truncate">
                    {{ $wali->address ?? '-' }}
                </td>
                <td>
                    <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded-md text-xs font-bold">
                        {{ $wali->santris_count }} Santri
                    </span>
                </td>
                <td>
                    <div class="flex items-center gap-2">
                        @if($isSuperAdmin)
                            <a href="{{ route('admin.wali.edit', $wali) }}" class="p-1 text-slate-400 hover:text-amber-500 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                        @endif
                        @if($isSuperAdmin)
                            <form action="{{ route('admin.wali.destroy', $wali) }}" method="POST" onsubmit="return confirm('Hapus wali ini? Akun user juga akan terhapus.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1 text-slate-400 hover:text-red-500 transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-slate-400">View only</span>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $walis->links() }}
    </div>
</div>
@endsection
