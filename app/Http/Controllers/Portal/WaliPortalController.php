<?php

namespace App\Http\Controllers\Portal;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PaymentProviderConfig;
use App\Models\ProductCategory;
use App\Models\Wali;
use App\Services\Payment\PaymentManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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

        $paymentManager = app(PaymentManager::class);

        $topupProviders = PaymentProviderConfig::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->map(function ($config) use ($paymentManager) {
                if (! $this->providerConfigured($config->provider, $config->config ?? [])) {
                    return null;
                }

                try {
                    $provider = $paymentManager->provider($config->provider);
                } catch (\Throwable $exception) {
                    return null;
                }

                if (! $provider->supports('wallet_topup')) {
                    return null;
                }

                return [
                    'key' => $config->provider,
                    'label' => $config->name ?: Str::upper($config->provider),
                ];
            })
            ->filter()
            ->values();

        if ($topupProviders->isEmpty()) {
            $fallbackKey = config('smart.payments.default_provider', 'ipaymu');
            $topupProviders = collect([[
                'key' => $fallbackKey,
                'label' => Str::upper($fallbackKey),
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

    protected function providerConfigured(string $providerKey, array $dbConfig): bool
    {
        return app(PaymentManager::class)->isProviderConfigured($providerKey, $dbConfig);
    }
}
