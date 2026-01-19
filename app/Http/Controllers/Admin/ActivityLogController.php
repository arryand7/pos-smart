<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\Exports\ExportsTable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    use ExportsTable;

    public function index(Request $request)
    {
        $query = ActivityLog::with('user');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        $sort = $request->string('sort')->value();
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';

        $query->when($sort, function ($builder) use ($sort, $direction) {
            return match ($sort) {
                'created_at', 'action', 'ip_address' => $builder->orderBy($sort, $direction),
                'user' => $builder->orderBy(
                    \App\Models\User::select('name')->whereColumn('users.id', 'activity_logs.user_id'),
                    $direction
                ),
                default => $builder->orderByDesc('created_at'),
            };
        }, fn ($builder) => $builder->orderByDesc('created_at'));

        if ($exportType = $this->exportType($request)) {
            $rows = $query->get()->map(function (ActivityLog $log) {
                return [
                    optional($log->created_at)->format('d M Y H:i'),
                    $log->user?->name ?? 'System',
                    strtoupper($log->action),
                    $log->description ?? '-',
                    $log->ip_address ?? '-',
                ];
            })->all();

            $headings = ['Waktu', 'Pengguna', 'Aksi', 'Deskripsi', 'IP Address'];

            return $this->exportTable($exportType, 'activity-logs', $headings, $rows);
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        $logs = $query->paginate($perPage)->withQueryString();

        return view('admin.activity-logs.index', compact('logs'));
    }
}
