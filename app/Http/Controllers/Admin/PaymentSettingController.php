<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentProviderConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentSettingController extends Controller
{
    public function index(): View
    {
        $providers = PaymentProviderConfig::orderBy('priority')->get();

        // Default providers if none exist
        if ($providers->isEmpty()) {
            $defaultProviders = [
                ['provider' => 'ipaymu', 'name' => 'iPaymu', 'is_active' => false, 'priority' => 1],
                ['provider' => 'midtrans', 'name' => 'Midtrans', 'is_active' => false, 'priority' => 2],
                ['provider' => 'doku', 'name' => 'DOKU', 'is_active' => false, 'priority' => 3],
            ];

            foreach ($defaultProviders as $p) {
                PaymentProviderConfig::create($p);
            }

            $providers = PaymentProviderConfig::orderBy('priority')->get();
        }

        return view('admin.settings.payments.index', compact('providers'));
    }

    public function edit(string $provider): View
    {
        $config = PaymentProviderConfig::where('provider', $provider)->firstOrFail();

        return view('admin.settings.payments.edit', compact('config'));
    }

    public function midtransChecklist(): View
    {
        $config = PaymentProviderConfig::where('provider', 'midtrans')->first();

        $credentials = $config?->config['credentials'] ?? [];

        $data = [
            'mode' => $config?->config['mode'] ?? config('smart.payments.providers.midtrans.mode', 'sandbox'),
            'merchant_id' => $credentials['merchant_id'] ?? ($config?->config['merchant_id'] ?? ''),
            'client_key' => $credentials['client_key'] ?? ($config?->config['client_key'] ?? ''),
            'server_key' => $credentials['server_key'] ?? ($config?->config['server_key'] ?? ''),
        ];

        $urls = [
            'payment_notification' => url('/api/payments/webhook/midtrans'),
            'recurring_notification' => url('/api/payments/webhook/midtrans'),
            'pay_account_notification' => url('/api/payments/webhook/midtrans'),
            'finish_redirect' => url('/payments/midtrans/redirect?status=success'),
            'unfinish_redirect' => url('/payments/midtrans/redirect?status=pending'),
            'error_redirect' => url('/payments/midtrans/redirect?status=failed'),
        ];

        return view('admin.settings.payments.midtrans-checklist', compact('data', 'urls'));
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        $config = PaymentProviderConfig::where('provider', $provider)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'priority' => ['required', 'integer', 'min:1'],
            'config' => ['nullable', 'array'],
            'sandbox_config' => ['nullable', 'array'],
        ]);

        $config->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? false,
            'priority' => $validated['priority'],
            'config' => $validated['config'] ?? $config->config,
            'sandbox_config' => $validated['sandbox_config'] ?? $config->sandbox_config,
        ]);

        return redirect()->route('admin.settings.payments')
            ->with('status', "Konfigurasi {$config->name} berhasil disimpan.");
    }

    public function toggleActive(string $provider): RedirectResponse
    {
        $config = PaymentProviderConfig::where('provider', $provider)->firstOrFail();

        $config->update([
            'is_active' => !$config->is_active,
        ]);

        $status = $config->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()
            ->with('status', "{$config->name} berhasil {$status}.");
    }

    public function updatePriority(Request $request, string $provider): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ]);

        $config = PaymentProviderConfig::where('provider', $provider)->firstOrFail();
        $currentPriority = $config->priority;

        if ($validated['direction'] === 'up' && $currentPriority > 1) {
            // Find the provider with the priority above this one
            $swapWith = PaymentProviderConfig::where('priority', $currentPriority - 1)->first();
            if ($swapWith) {
                $swapWith->update(['priority' => $currentPriority]);
                $config->update(['priority' => $currentPriority - 1]);
            }
        } elseif ($validated['direction'] === 'down') {
            // Find the provider with the priority below this one
            $swapWith = PaymentProviderConfig::where('priority', $currentPriority + 1)->first();
            if ($swapWith) {
                $swapWith->update(['priority' => $currentPriority]);
                $config->update(['priority' => $currentPriority + 1]);
            }
        }

        return redirect()->back()->with('status', "Prioritas {$config->name} berhasil diperbarui.");
    }
}
