@extends('layouts.admin')

@section('title', 'Data Wali Santri')
@section('subtitle', 'Manajemen akun orang tua/wali santri.')

@section('actions')
    <a href="{{ route('admin.wali.create') }}" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Wali
    </a>
@endsection

@section('content')
<div class="admin-card">
    <div class="table-scroll">
        <table class="datatable w-full">
        <thead>
            <tr>
                <th>Nama Wali</th>
                <th>Kontak</th>
                <th>Alamat</th>
                <th>Jumlah Santri</th>
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
                        <a href="{{ route('admin.wali.edit', $wali) }}" class="p-1 text-slate-400 hover:text-amber-500 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                        <form action="{{ route('admin.wali.destroy', $wali) }}" method="POST" onsubmit="return confirm('Hapus wali ini? Akun user juga akan terhapus.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1 text-slate-400 hover:text-red-500 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
        </table>
    </div>
</div>
@endsection
