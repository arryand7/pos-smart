<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Santri;
use App\Services\Accounting\AccountingService;
use App\Services\Wallet\WalletService;
use App\Support\Rupiah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletManagementController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly AccountingService $accountingService,
    ) {}

    public function index(Request $request): View
    {
        $query = Santri::query()->with('wali')->latest();

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%")
                    ->orWhereHas('wali', fn ($w) => $w->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('is_wallet_locked', $request->input('status') === 'locked');
        }

        $santris = $query->paginate(20)->withQueryString();

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
            'amount' => ['required', 'integer', 'min:1000'],
            'channel' => ['required', 'string', 'in:cash,bank_transfer,other'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $validated['amount'] = Rupiah::from($validated['amount'], 'top_up_amount');

        $walletTx = $this->walletService->credit($santri, $validated['amount'], [
            'performed_by' => auth()->id(),
            'channel' => $validated['channel'],
            'description' => $validated['description'] ?? 'Top up manual oleh admin',
        ]);

        // Record accounting journal for manual top-up
        $payment = Payment::create([
            'provider' => 'manual',
            'amount' => $validated['amount'],
            'currency' => 'IDR',
            'status' => 'paid',
            'channel' => $validated['channel'],
            'santri_id' => $santri->id,
            'metadata' => [
                'wallet_transaction_id' => $walletTx->id,
                'performed_by' => auth()->id(),
            ],
        ]);
        $this->accountingService->recordWalletTopUp($payment);

        ActivityLog::log('topup', 'Top up manual Rp'.number_format($validated['amount'], 0, ',', '.').' untuk '.$santri->name, $santri, [
            'amount' => $validated['amount'],
            'channel' => $validated['channel'],
        ]);

        return redirect()->route('admin.wallets.show', $santri)
            ->with('status', 'Top up berhasil! Saldo bertambah Rp'.number_format($validated['amount'], 0, ',', '.'));
    }

    public function adjustBalance(Request $request, Santri $santri): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $validated['amount'] = Rupiah::from($validated['amount'], 'adjustment_amount');

        if ($validated['type'] === 'credit') {
            $this->walletService->credit($santri, $validated['amount'], [
                'performed_by' => auth()->id(),
                'channel' => 'adjustment',
                'description' => 'Koreksi tambah: '.$validated['reason'],
            ]);
        } else {
            $this->walletService->debit($santri, $validated['amount'], [
                'performed_by' => auth()->id(),
                'channel' => 'adjustment',
                'description' => 'Koreksi kurang: '.$validated['reason'],
            ]);
        }

        ActivityLog::log('adjustment', 'Koreksi saldo ('.$validated['type'].') Rp'.number_format($validated['amount'], 0, ',', '.').' untuk '.$santri->name.': '.$validated['reason'], $santri, [
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'reason' => $validated['reason'],
        ]);

        return redirect()->route('admin.wallets.show', $santri)
            ->with('status', 'Koreksi saldo berhasil diterapkan.');
    }

    public function toggleLock(Santri $santri): RedirectResponse
    {
        $santri->update([
            'is_wallet_locked' => ! $santri->is_wallet_locked,
        ]);

        $status = $santri->is_wallet_locked ? 'dikunci' : 'dibuka';

        ActivityLog::log('wallet_lock', "Wallet santri {$santri->name} {$status}", $santri, [
            'is_locked' => $santri->is_wallet_locked,
        ]);

        return redirect()->back()
            ->with('status', "Wallet santri {$santri->name} berhasil {$status}.");
    }
}
