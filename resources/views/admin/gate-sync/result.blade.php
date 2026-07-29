@extends('layouts.admin')
@section('title', 'Gate Sync Result')
@section('subtitle', $batch->uuid)
@section('actions')
@if($batch->report_status === 'pending')<form method="POST" action="{{ route('admin.gate-sync.retry', $batch) }}">@csrf<button class="btn btn-primary">Retry Report to Gate</button></form>@endif
@endsection
@section('content')
<div class="stat-grid"><div class="stat-card"><span class="stat-label">Batch status</span><span class="stat-value">{{ $batch->status }}</span></div><div class="stat-card"><span class="stat-label">Gate report</span><span class="stat-value">{{ $batch->report_status }}</span><span class="stat-meta">{{ $batch->report_attempts }} attempts</span></div></div>
<div class="card"><div class="table-scroll"><table class="table"><thead><tr><th>Gate UUID</th><th>Category</th><th>Action</th><th>Result</th><th>Local ID</th><th>Error</th></tr></thead><tbody>@foreach($batch->items as $item)<tr><td>{{ $item->gate_user_uuid ?? '—' }}</td><td>{{ $item->category }}</td><td>{{ $item->selected_action ?? '—' }}</td><td>{{ $item->result_status ?? '—' }}</td><td>{{ $item->external_user_id ?? '—' }}</td><td>{{ $item->error_code ?? '—' }}</td></tr>@endforeach</tbody></table></div>@if($batch->items->contains('category','conflict'))<a class="btn btn-outline mt-4" href="{{ route('admin.gate-sync.conflicts', $batch) }}">Download conflict report</a>@endif</div>
@endsection
