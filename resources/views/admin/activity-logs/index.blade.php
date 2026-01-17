@extends('layouts.admin')

@section('title', 'Activity Logs')
@section('subtitle', 'Riwayat aktivitas dan perubahan sistem')

@section('content')
<div class="card">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h2 class="text-xl font-semibold text-slate-800">Log Aktivitas</h2>
        <span class="text-sm text-slate-500">Total: {{ $logs->count() }} log</span>
    </div>

    <div class="table-scroll">
        <table class="datatable w-full">
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Pengguna</th>
                <th>Aksi</th>
                <th>Deskripsi</th>
                <th>IP Address</th>
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
</div>
@endsection
