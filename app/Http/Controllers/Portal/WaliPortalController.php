<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\ProductCategory;
use App\Models\Wali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WaliPortalController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $isSuperAdmin = $user?->hasRole(UserRole::SUPER_ADMIN) ?? false;

        if (! $user || (! $user->wali && ! $isSuperAdmin)) {
            abort(403, 'Akun wali tidak ditemukan.');
        }

        $activeWaliId = null;
        $waliOptions = collect();
        $wali = $user->wali;

        if ($isSuperAdmin) {
            $activeWaliId = $request->integer('wali_id');
            $wali = $activeWaliId
                ? Wali::find($activeWaliId)
                : Wali::orderBy('name')->first();

            if (! $wali) {
                abort(404, 'Belum ada data wali.');
            }

            $waliOptions = Wali::orderBy('name')->get(['id', 'name']);
        }

        $santris = $wali->santris()
            ->with([
                'walletTransactions' => fn ($q) => $q->latest('occurred_at')->limit(5),
                'payments' => fn ($q) => $q->latest('created_at')->limit(5),
            ])
            ->orderBy('name')
            ->get();

        $categoryIds = $santris->flatMap(function ($santri) {
            return collect($santri->blocked_category_ids)
                ->merge($santri->whitelisted_category_ids ?? []);
        })->filter()->unique()->values()->all();

        $categoryNames = ProductCategory::whereIn('id', $categoryIds)
            ->pluck('name', 'id');

        $stats = [
            'total_children' => $santris->count(),
            'total_balance' => $santris->sum('wallet_balance'),
            'locked_wallets' => $santris->where('is_wallet_locked', true)->count(),
        ];

        $topupProviders = collect(config('smart.payments.providers', []))
            ->map(function ($config, $key) {
                return [
                    'key' => $key,
                    'label' => Str::upper($config['label'] ?? $key),
                    'capabilities' => Arr::get($config, 'capabilities', []),
                ];
            })
            ->filter(fn ($provider) => in_array('wallet_topup', $provider['capabilities'], true))
            ->values();

        if ($topupProviders->isEmpty()) {
            $topupProviders = collect([[
                'key' => config('smart.payments.default_provider', 'ipaymu'),
                'label' => Str::upper(config('smart.payments.default_provider', 'ipaymu')),
            ]]);
        }

        // Get all categories for blocking form
        $allCategories = ProductCategory::orderBy('name')->get(['id', 'name']);

        return view('portal.wali', [
            'wali' => $wali,
            'santris' => $santris,
            'stats' => $stats,
            'categoryNames' => $categoryNames,
            'topupProviders' => $topupProviders,
            'allCategories' => $allCategories,
            'isSuperAdmin' => $isSuperAdmin,
            'waliOptions' => $waliOptions,
            'activeWaliId' => $activeWaliId ?? $wali?->id,
        ]);
    }
}
