<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingSettingController extends Controller
{
    public function edit(): View
    {
        $settings = AppSetting::first() ?? new AppSetting();
        $accounts = Account::orderBy('code')->get();

        return view('admin.settings.accounting', compact('settings', 'accounts'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_cash' => ['nullable', 'string', 'max:20'],
            'account_wallet_liability' => ['nullable', 'string', 'max:20'],
            'account_revenue' => ['nullable', 'string', 'max:20'],
            'account_inventory' => ['nullable', 'string', 'max:20'],
            'account_cogs' => ['nullable', 'string', 'max:20'],
        ]);

        $settings = AppSetting::firstOrCreate([]);
        $settings->update($validated);

        return redirect()->route('admin.settings.accounting')
            ->with('status', 'Pengaturan akuntansi berhasil disimpan.');
    }
}
