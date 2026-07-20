<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\Transaction;
use App\Support\Rupiah;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingService
{
    public function recordPosTransaction(Transaction $transaction): ?JournalEntry
    {
        if (JournalEntry::query()
            ->where('source_type', Transaction::class)
            ->where('source_id', $transaction->id)
            ->where('journal_type', 'primary')
            ->exists()) {
            return null;
        }

        return DB::transaction(function () use ($transaction) {
            $cashAccount = $this->resolveAccount(config('smart.accounting.accounts.cash'));
            $walletAccount = $this->resolveAccount(config('smart.accounting.accounts.wallet_liability'));
            $revenueAccount = $this->resolveAccount(config('smart.accounting.accounts.revenue'));

            if (! $cashAccount || ! $walletAccount || ! $revenueAccount) {
                return null;
            }

            $total = Rupiah::from($transaction->total_amount, 'transaction.total_amount');
            $walletAmount = Rupiah::from($transaction->wallet_amount, 'transaction.wallet_amount');
            $cashAmount = Rupiah::from($transaction->cash_amount, 'transaction.cash_amount');
            $gatewayAmount = Rupiah::from($transaction->gateway_amount, 'transaction.gateway_amount');

            $entry = JournalEntry::create([
                'reference' => 'POS-'.$transaction->reference,
                'entry_date' => $transaction->processed_at?->toDateString() ?? now()->toDateString(),
                'status' => 'posted',
                'description' => 'Penjualan POS '.$transaction->reference,
                'source_type' => Transaction::class,
                'source_id' => $transaction->id,
                'journal_type' => 'primary',
                'total_debit' => $total,
                'total_credit' => $total,
            ]);

            if ($cashAmount > 0) {
                $this->createLine($entry, $cashAccount, 'debit', $cashAmount, 'Kasir POS');
            }

            if ($gatewayAmount > 0) {
                $this->createLine($entry, $cashAccount, 'debit', $gatewayAmount, 'Pembayaran Gateway');
            }

            if ($walletAmount > 0) {
                $this->createLine($entry, $walletAccount, 'debit', $walletAmount, 'Penggunaan saldo santri');
            }

            $this->createLine($entry, $revenueAccount, 'credit', $total, 'Pendapatan penjualan');

            return $entry->load('lines');
        });
    }

    public function recordWalletTopUp(Payment $payment): ?JournalEntry
    {
        return DB::transaction(function () use ($payment) {
            $cashAccount = $this->resolveAccount(config('smart.accounting.accounts.cash'));
            $walletAccount = $this->resolveAccount(config('smart.accounting.accounts.wallet_liability'));

            if (! $cashAccount || ! $walletAccount) {
                return null;
            }

            $amount = Rupiah::from($payment->amount, 'payment.amount');
            $entry = JournalEntry::create([
                'reference' => 'TOPUP-'.($payment->provider_reference ?: Str::upper(Str::random(8))),
                'entry_date' => now()->toDateString(),
                'status' => 'posted',
                'description' => 'Top up saldo santri',
                'source_type' => Payment::class,
                'source_id' => $payment->id,
                'total_debit' => $amount,
                'total_credit' => $amount,
            ]);

            $this->createLine($entry, $cashAccount, 'debit', $amount, 'Dana diterima');
            $this->createLine($entry, $walletAccount, 'credit', $amount, 'Saldo dompet santri');

            return $entry->load('lines');
        });
    }

    public function reversePosTransaction(Transaction $transaction, ?string $reason = null): ?JournalEntry
    {
        $entry = JournalEntry::query()
            ->where('source_type', Transaction::class)
            ->where('source_id', $transaction->id)
            ->latest('id')
            ->first();

        if (! $entry) {
            return null;
        }

        $alreadyReversed = JournalEntry::query()
            ->where('reference', 'REV-'.$entry->reference)
            ->exists();

        if ($alreadyReversed) {
            return null;
        }

        $entry->loadMissing('lines.account');

        return DB::transaction(function () use ($entry, $transaction, $reason) {
            $reversal = JournalEntry::create([
                'reference' => 'REV-'.$entry->reference,
                'entry_date' => now()->toDateString(),
                'status' => 'posted',
                'description' => 'Pembatalan '.$entry->description,
                'source_type' => Transaction::class,
                'source_id' => $transaction->id,
                'journal_type' => 'reversal',
                'total_debit' => $entry->total_credit,
                'total_credit' => $entry->total_debit,
                'metadata' => array_filter([
                    'reversal_of' => $entry->id,
                    'reason' => $reason,
                ]),
            ]);

            foreach ($entry->lines as $line) {
                $type = $line->type === 'debit' ? 'credit' : 'debit';
                $memo = $line->memo ? 'Reversal: '.$line->memo : 'Reversal entry';
                $this->createLine($reversal, $line->account, $type, Rupiah::from($line->amount, 'journal_line.amount'), $memo);
            }

            return $reversal->load('lines');
        });
    }

    protected function createLine(JournalEntry $entry, Account $account, string $type, int $amount, ?string $memo = null): JournalLine
    {
        return JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'type' => $type,
            'amount' => $amount,
            'memo' => $memo,
        ]);
    }

    protected function resolveAccount(?string $code): ?Account
    {
        if (! $code) {
            return null;
        }

        return Account::query()->where('code', $code)->where('is_active', true)->first();
    }
}
