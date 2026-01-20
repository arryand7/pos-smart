@extends('layouts.admin')

@section('title', 'Activity Logs')
@section('subtitle', 'Riwayat aktivitas dan perubahan sistem')

@section('content')
<div class="card">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h2 class="text-xl font-semibold text-slate-800">Log Aktivitas</h2>
        <span class="text-sm text-slate-500">Total: {{ method_exists($logs, 'total') ? $logs->total() : $logs->count() }} log</span>
    </div>

    <form method="GET" class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <label class="text-xs font-semibold text-slate-500">
                Cari
                <input type="text" name="search" value="{{ request('search') }}" class="form-input mt-1 w-64" placeholder="User, aksi, IP...">
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
                <x-sortable-th field="created_at" label="Waktu" />
                <x-sortable-th field="user" label="Pengguna" />
                <x-sortable-th field="action" label="Aksi" />
                <th>Deskripsi</th>
                <x-sortable-th field="ip_address" label="IP Address" />
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            <tr>
                <td class="whitespace-nowrap">{{ $log->created_at?->format('d M Y H:i') }}</td>
                <td>
                    @if($log->user)
                        <strong>{{ $log->user->name }}</strong>
                        <br><small class="text-slate-500">{{ $log->user->role }}</small>
                    @else
                        <span class="text-slate-400">System</span>
                    @endif
                </td>
                <td>
                    @php
                        $actionClasses = [
                            'created' => 'badge-success',
                            'updated' => 'badge-info',
                            'deleted' => 'badge-danger',
                            'logged_in' => 'badge-info',
                            'logged_out' => 'badge-warning',
                            'cancelled' => 'badge-danger',
                        ];
                        $badgeClass = $actionClasses[$log->action] ?? 'badge';
                    @endphp
                    <span class="badge {{ $badgeClass }}">
                        {{ strtoupper($log->action) }}
                    </span>
                </td>
                <td>
                    {{ $log->description ?? '-' }}
                    @if($log->subject_type)
                        <br><small class="text-slate-500">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</small>
                    @endif
                </td>
                <td class="text-xs text-slate-500">{{ $log->ip_address ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center py-10 text-slate-400">
                    Belum ada log aktivitas.
                </td>
            </tr>
            @endforelse
        </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $logs->links() }}
    </div>
</div>
@endsection
