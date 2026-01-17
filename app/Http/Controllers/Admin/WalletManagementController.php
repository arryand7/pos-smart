<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Services\Wallet\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletManagementController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService
    ) {}

    public function index(Request $request): View
    {
        $query = Santri::query()->with('wali')->latest();

        $santris = $query->get();

        return view('admin.wallets.index', compact('santris'));
    }

    public function show(Santri $santri): View
    {
        $santri->load('wali');
        
        $transactions = $santri->walletTransactions()
            ->with('performer')
            ->latest('occurred_at')
            ->paginate(20);

        return view('admin.wallets.show', compact('santri', 'transactions'));
    }

    public function topUp(Santri $santri): View
    {
        return view('admin.wallets.topup', compact('santri'));
    }

    public function storeTopUp(Request $request, Santri $santri): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000'],
            'channel' => ['required', 'string', 'in:cash,bank_transfer,other'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $transaction = $this->walletService->credit($santri, $validated['amount'], [
            'performed_by' => auth()->id(),
            'channel' => $validated['channel'],
            'description' => $validated['description'] ?? 'Top up manual oleh admin',
        ]);

        return redirect()->route('admin.wallets.show', $santri)
            ->with('status', 'Top up berhasil! Saldo bertambah Rp' . number_format($validated['amount'], 0, ',', '.'));
    }

    public function adjustBalance(Request $request, Santri $santri): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($validated['type'] === 'credit') {
            $this->walletService->credit($santri, $validated['amount'], [
                'performed_by' => auth()->id(),
                'channel' => 'adjustment',
                'description' => 'Koreksi tambah: ' . $validated['reason'],
            ]);
        } else {
            $this->walletService->debit($santri, $validated['amount'], [
                'performed_by' => auth()->id(),
                'channel' => 'adjustment',
                'description' => 'Koreksi kurang: ' . $validated['reason'],
            ]);
        }

        return redirect()->route('admin.wallets.show', $santri)
            ->with('status', 'Koreksi saldo berhasil diterapkan.');
    }

    public function toggleLock(Santri $santri): RedirectResponse
    {
        $santri->update([
            'is_wallet_locked' => !$santri->is_wallet_locked,
        ]);

        $status = $santri->is_wallet_locked ? 'dikunci' : 'dibuka';

        return redirect()->back()
            ->with('status', "Wallet santri {$santri->name} berhasil {$status}.");
    }
}
