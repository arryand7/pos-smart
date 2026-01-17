@extends('layouts.admin')

@section('title', 'Manajemen Pengguna')
@section('subtitle', 'Kelola akun administrator, bendahara, dan kasir.')

@section('actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Pengguna
    </a>
@endsection

@section('content')
<div class="admin-card">
    <div class="table-scroll">
        <table class="datatable w-full">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Bergabung</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center font-bold text-[#007A5C] text-xs">
                            {{ substr($user->name, 0, 2) }}
                        </div>
                        <div class="font-medium text-[#0f172a]">{{ $user->name }}</div>
                    </div>
                </td>
                <td class="text-slate-500">{{ $user->email }}</td>
                <td>
                    @php
                        $roles = collect($user->roles ?? [])
                            ->push($user->role?->value ?? $user->role)
                            ->filter()
                            ->unique()
                            ->values();
                    @endphp
                    <div class="flex flex-wrap gap-2">
                        @foreach($roles as $role)
                            @if($role === \App\Enums\UserRole::SUPER_ADMIN->value)
                                <span class="px-2 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700">SUPER ADMIN</span>
                            @elseif($role === \App\Enums\UserRole::ADMIN->value)
                                <span class="px-2 py-1 rounded-lg text-xs font-bold bg-violet-50 text-violet-700">ADMIN</span>
                            @elseif($role === \App\Enums\UserRole::BENDAHARA->value)
                                <span class="px-2 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-700">BENDAHARA</span>
                            @elseif($role === \App\Enums\UserRole::KASIR->value)
                                <span class="px-2 py-1 rounded-lg text-xs font-bold bg-sky-50 text-sky-700">KASIR</span>
                            @else
                                <span class="px-2 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600">{{ strtoupper($role) }}</span>
                            @endif
                        @endforeach
                    </div>
                </td>
                <td><span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2 py-1 rounded-md">Aktif</span></td>
                <td class="text-slate-500 text-xs">{{ $user->created_at->format('d M Y') }}</td>
                <td>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.users.edit', $user) }}" class="p-1 text-slate-400 hover:text-amber-500 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                        @if($user->id !== auth()->id())
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Hapus pengguna ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1 text-slate-400 hover:text-red-500 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
        </table>
    </div>
</div>
@endsection
