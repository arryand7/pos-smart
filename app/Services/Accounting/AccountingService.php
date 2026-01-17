<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingService
{
    public function recordPosTransaction(Transaction $transaction): ?JournalEntry
    {
        return DB::transaction(function () use ($transaction) {
            $cashAccount = $this->resolveAccount(config('smart.accounting.accounts.cash'), 'Kas');
            $walletAccount = $this->resolveAccount(config('smart.accounting.accounts.wallet_liability'), 'Utang Saldo Santri');
            $revenueAccount = $this->resolveAccount(config('smart.accounting.accounts.revenue'), 'Pendapatan Penjualan');

            if (! $cashAccount || ! $walletAccount || ! $revenueAccount) {
                return null;
            }

            $entry = JournalEntry::create([
                'reference' => 'POS-'.$transaction->reference,
                'entry_date' => $transaction->processed_at?->toDateString() ?? now()->toDateString(),
                'status' => 'posted',
                'description' => 'Penjualan POS '.$transaction->reference,
                'source_type' => Transaction::class,
                'source_id' => $transaction->id,
                'total_debit' => $transaction->total_amount,
                'total_credit' => $transaction->total_amount,
            ]);

            if ($transaction->cash_amount > 0) {
                $this->createLine($entry, $cashAccount, 'debit', $transaction->cash_amount, 'Kasir POS');
            }

            if ($transaction->gateway_amount > 0) {
                $this->createLine($entry, $cashAccount, 'debit', $transaction->gateway_amount, 'Pembayaran Gateway');
            }

            if ($transaction->wallet_amount > 0) {
                $this->createLine($entry, $walletAccount, 'debit', $transaction->wallet_amount, 'Penggunaan saldo santri');
            }

            $this->createLine($entry, $revenueAccount, 'credit', $transaction->total_amount, 'Pendapatan penjualan');

            return $entry->load('lines');
        });
    }

    public function recordWalletTopUp(Payment $payment): ?JournalEntry
    {
        return DB::transaction(function () use ($payment) {
            $cashAccount = $this->resolveAccount(config('smart.accounting.accounts.cash'), 'Kas/Bank');
            $walletAccount = $this->resolveAccount(config('smart.accounting.accounts.wallet_liability'), 'Utang Saldo Santri');

            if (! $cashAccount || ! $walletAccount) {
                return null;
            }

            $entry = JournalEntry::create([
                'reference' => 'TOPUP-'.($payment->provider_reference ?: Str::upper(Str::random(8))),
                'entry_date' => now()->toDateString(),
                'status' => 'posted',
                'description' => 'Top up saldo santri',
                'source_type' => Payment::class,
                'source_id' => $payment->id,
                'total_debit' => $payment->amount,
                'total_credit' => $payment->amount,
            ]);

            $this->createLine($entry, $cashAccount, 'debit', $payment->amount, 'Dana diterima');
            $this->createLine($entry, $walletAccount, 'credit', $payment->amount, 'Saldo dompet santri');

            return $entry->load('lines');
        });
    }

    protected function createLine(JournalEntry $entry, Account $account, string $type, float $amount, ?string $memo = null): JournalLine
    {
        return JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'type' => $type,
            'amount' => $amount,
            'memo' => $memo,
        ]);
    }

    protected function resolveAccount(?string $code, string $fallbackName): ?Account
    {
        if (! $code) {
            return null;
        }

        return Account::firstOrCreate(
            ['code' => $code],
            [
                'name' => $fallbackName,
                'type' => $this->guessAccountType($code),
                'is_active' => true,
            ]
        );
    }

    protected function guessAccountType(string $code): string
    {
        return match (substr($code, 0, 1)) {
            '1' => 'asset',
            '2' => 'liability',
            '3' => 'equity',
            '4' => 'revenue',
            '5', '6' => 'expense',
            default => 'asset',
        };
    }
}
