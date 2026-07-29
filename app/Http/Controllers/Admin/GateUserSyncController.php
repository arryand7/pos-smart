<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApplyGateUserSyncRequest;
use App\Models\GateSyncBatch;
use App\Models\User;
use App\Services\Gate\GateProvisioningException;
use App\Services\Gate\GateUserSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GateUserSyncController extends Controller
{
    public function index(): View
    {
        return view('admin.gate-sync.index', [
            'configured' => filled(config('services.gate.url')) && filled(config('services.gate.provisioning_client_id')) && filled(config('services.gate.provisioning_client_secret')),
            'lastSuccess' => GateSyncBatch::where('report_status', 'sent')->latest('reported_at')->first(),
            'lastFailure' => GateSyncBatch::whereIn('status', ['failed', 'report_pending'])->latest()->first(),
            'pendingReports' => GateSyncBatch::where('report_status', 'pending')->count(),
            'totalUsers' => User::count(), 'linkedUsers' => User::whereNotNull('gate_user_uuid')->count(),
            'history' => GateSyncBatch::with('initiator')->latest()->paginate(15),
        ]);
    }

    public function preview(Request $request, GateUserSyncService $service): RedirectResponse
    {
        try {
            $batch = $service->preview($request->user());
        } catch (GateProvisioningException $e) {
            return back()->with('error', $e->errorCode.': '.$e->getMessage());
        }

        return redirect()->route('admin.gate-sync.show', $batch);
    }

    public function show(Request $request, GateSyncBatch $batch): View
    {
        $query = $batch->items()->orderBy('id');
        if ($category = $request->string('category')->value()) {
            $query->where('category', $category);
        }
        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q->where('gate_user_uuid', 'like', "%{$search}%")->orWhere('gate_payload->name', 'like', "%{$search}%")->orWhere('gate_payload->email', 'like', "%{$search}%"));
        }

        return view('admin.gate-sync.show', ['batch' => $batch, 'items' => $query->paginate(25)->withQueryString(), 'summary' => $batch->items()->selectRaw('category, count(*) as aggregate')->groupBy('category')->pluck('aggregate', 'category')]);
    }

    public function apply(ApplyGateUserSyncRequest $request, GateSyncBatch $batch, GateUserSyncService $service): RedirectResponse
    {
        $service->apply($batch, $request->validated('items', []), $request->user());

        return redirect()->route('admin.gate-sync.result', $batch)->with('status', 'Sinkronisasi lokal selesai.');
    }

    public function result(GateSyncBatch $batch): View
    {
        return view('admin.gate-sync.result', ['batch' => $batch->load('items')]);
    }

    public function retry(GateSyncBatch $batch, GateUserSyncService $service): RedirectResponse
    {
        $service->retryReport($batch);

        return back()->with('status', 'Percobaan pelaporan ke Gate selesai.');
    }

    public function conflicts(GateSyncBatch $batch)
    {
        $rows = $batch->items()->where('category', 'conflict')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['gate_user_uuid', 'name', 'email', 'error_code']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->gate_user_uuid, data_get($row->gate_payload, 'name'), data_get($row->gate_payload, 'email'), $row->error_code]);
            }
            fclose($out);
        }, "gate-conflicts-{$batch->uuid}.csv", ['Content-Type' => 'text/csv']);
    }
}
