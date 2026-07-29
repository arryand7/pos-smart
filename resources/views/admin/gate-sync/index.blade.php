@extends('layouts.admin')
@section('title', 'User Synchronization')
@section('subtitle', 'Pull-based identity reconciliation from Gate. Preview never mutates local users.')
@section('actions')
<form method="POST" action="{{ route('admin.gate-sync.preview') }}">@csrf
    <button class="btn btn-primary" @disabled(!$configured)>Sync Users from Gate</button>
</form>
@endsection
@section('content')
<div class="stat-grid">
    <div class="stat-card"><span class="stat-label">Gate configuration</span><span class="stat-value">{{ $configured ? 'Ready' : 'Incomplete' }}</span></div>
    <div class="stat-card"><span class="stat-label">Local users</span><span class="stat-value">{{ $totalUsers }}</span></div>
    <div class="stat-card"><span class="stat-label">Linked to Gate</span><span class="stat-value">{{ $linkedUsers }}</span><span class="stat-meta">{{ $totalUsers - $linkedUsers }} unlinked</span></div>
    <div class="stat-card"><span class="stat-label">Pending reports</span><span class="stat-value">{{ $pendingReports }}</span></div>
</div>
<div class="card">
    <div class="grid gap-3 md:grid-cols-2 text-sm mb-5"><p>Last success: {{ $lastSuccess?->reported_at?->format('d M Y H:i') ?? '—' }}</p><p>Last failure: {{ $lastFailure?->updated_at?->format('d M Y H:i') ?? '—' }}</p></div>
    <h3 class="text-lg font-semibold mb-4">Sync history</h3>
    <div class="table-scroll"><table class="table"><thead><tr><th>Batch</th><th>Initiator</th><th>Status</th><th>Items</th><th>Created</th></tr></thead><tbody>
    @forelse($history as $batch)<tr><td><a class="text-blue-700" href="{{ route('admin.gate-sync.show', $batch) }}">{{ $batch->uuid }}</a></td><td>{{ $batch->initiator?->name }}</td><td><span class="badge">{{ $batch->status }}</span></td><td>{{ $batch->total_items }}</td><td>{{ $batch->created_at->format('d M Y H:i') }}</td></tr>@empty<tr><td colspan="5">No sync batches yet.</td></tr>@endforelse
    </tbody></table></div>{{ $history->links() }}
</div>
@endsection
