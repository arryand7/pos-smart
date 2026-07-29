@extends('layouts.admin')
@section('title', 'Gate Sync Preview')
@section('subtitle', $batch->uuid.' · expires '.$batch->expires_at->format('d M Y H:i'))
@section('content')
@php($categories = ['matched','needs_update','missing_in_application','access_revoked','inactive_in_gate','reactivation_required','local_only','conflict'])
<div class="grid gap-3 grid-cols-2 md:grid-cols-4">@foreach($categories as $category)<a href="?category={{ $category }}" class="card !p-4"><span class="text-xs text-slate-500">{{ str_replace('_',' ',$category) }}</span><strong class="block text-2xl">{{ $summary[$category] ?? 0 }}</strong></a>@endforeach</div>
@if($batch->expires_at->isPast() && !$batch->applied_at)<div class="alert alert-danger">This preview has expired and cannot be applied.</div>@endif
<div class="card">
<form method="GET" class="flex gap-3 mb-4"><input class="form-input" name="search" value="{{ request('search') }}" placeholder="Search UUID, name or email"><button class="btn btn-outline">Search</button></form>
<form method="POST" action="{{ route('admin.gate-sync.apply', $batch) }}" onsubmit="return confirm('Apply these reviewed actions? This can only run once.')">@csrf
<div class="table-scroll"><table class="table"><thead><tr><th>User</th><th>Gate / local role</th><th>Status</th><th>Category</th><th>Differences</th><th>Action</th></tr></thead><tbody>
@forelse($items as $item)<tr><td><div class="font-semibold">{{ data_get($item->gate_payload,'name',data_get($item->local_payload,'name','—')) }}</div><div class="text-xs">{{ $item->gate_user_uuid ?? 'local only' }}<br>{{ data_get($item->gate_payload,'email',data_get($item->local_payload,'email')) }}</div></td><td>{{ data_get($item->gate_payload,'role','—') }} / {{ data_get($item->local_payload,'role','—') }}</td><td>{{ data_get($item->gate_payload,'identity_active') ? 'active' : 'inactive' }} / {{ data_get($item->local_payload,'status','—') }}</td><td><span class="badge">{{ $item->category }}</span>@if($item->error_code)<div class="text-xs text-rose-700">{{ $item->error_code }}</div>@endif</td><td><details><summary>{{ count($item->differences ?? []) }} fields</summary><pre class="text-xs whitespace-pre-wrap">{{ json_encode($item->differences, JSON_PRETTY_PRINT) }}</pre></details></td><td><input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}"><select class="form-select" name="items[{{ $loop->index }}][action]">@foreach(\App\Services\Gate\GateUserReconciliationService::ACTIONS[$item->category] as $action)<option @selected($action === $item->recommended_action)>{{ $action }}</option>@endforeach</select></td></tr>
@empty<tr><td colspan="6">No users in this view.</td></tr>@endforelse
</tbody></table></div>
@if($batch->status === 'ready' && !$batch->expires_at->isPast())<div class="mt-5 flex justify-end"><button class="btn btn-primary">Review and apply</button></div>@endif
</form>{{ $items->links() }}</div>
@endsection
