<?php

namespace App\Services\Wallet;

use App\Models\Santri;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    public function credit(Santri $santri, float $amount, array $context = []): WalletTransaction
    {
        return DB::transaction(function () use ($santri, $amount, $context) {
            $before = $santri->wallet_balance;
            $after = $before + $amount;

            $santri->update(['wallet_balance' => $after]);

            return $this->storeTransaction($santri, $amount, $before, $after, array_merge($context, [
                'type' => $context['type'] ?? 'credit',
            ]));
        });
    }

    public function debit(Santri $santri, float $amount, array $context = []): WalletTransaction
    {
        return DB::transaction(function () use ($santri, $amount, $context) {
            $this->assertSufficientBalance($santri, $amount);
            $this->assertWithinLimit($santri, $amount);

            $before = $santri->wallet_balance;
            $after = $before - $amount;

            $santri->update(['wallet_balance' => $after]);

            return $this->storeTransaction($santri, $amount * -1, $before, $after, array_merge($context, [
                'type' => $context['type'] ?? 'debit',
            ]));
        });
    }

    protected function storeTransaction(Santri $santri, float $amount, float $before, float $after, array $context): WalletTransaction
    {
        /** @var WalletTransaction $transaction */
        $transaction = new WalletTransaction([
            'type' => $context['type'] ?? ($amount >= 0 ? 'credit' : 'debit'),
            'channel' => $context['channel'] ?? null,
            'amount' => abs($amount),
            'balance_before' => $before,
            'balance_after' => $after,
            'status' => $context['status'] ?? 'completed',
            'description' => $context['description'] ?? null,
            'occurred_at' => $context['occurred_at'] ?? now(),
            'metadata' => $context['metadata'] ?? null,
        ]);

        if (isset($context['performed_by'])) {
            $actor = $context['performed_by'];
            $transaction->performed_by = $actor instanceof Model ? $actor->getKey() : $actor;
        }

        $transaction->santri()->associate($santri);

        if ($context['reference'] ?? null) {
            $this->associateReference($transaction, $context['reference']);
        }

        $transaction->save();

        return $transaction;
    }

    protected function associateReference(WalletTransaction $transaction, mixed $reference): void
    {
        if ($reference instanceof Model) {
            $transaction->reference()->associate($reference);
        } elseif (is_array($reference)) {
            $transaction->reference_type = $reference['type'] ?? null;
            $transaction->reference_id = $reference['id'] ?? null;
        }
    }

    protected function assertSufficientBalance(Santri $santri, float $amount): void
    {
        if ($santri->wallet_balance < $amount) {
            throw new RuntimeException('Saldo dompet santri tidak mencukupi.');
        }
    }

    protected function assertWithinLimit(Santri $santri, float $amount): void
    {
        if ($santri->is_wallet_locked) {
            throw new RuntimeException('Dompet santri sedang diblokir oleh wali.');
        }

        $dailyLimit = $santri->daily_limit ?: config('smart.wallet.default_daily_limit', 0);
        $weeklyLimit = $santri->weekly_limit ?: config('smart.wallet.default_weekly_limit', 200000);
        $monthlyLimit = $santri->monthly_limit ?: config('smart.wallet.default_monthly_limit', 0);

        $now = now();

        if ($dailyLimit > 0) {
            $spentToday = WalletTransaction::query()
                ->where('santri_id', $santri->id)
                ->where('type', 'debit')
                ->where('status', 'completed')
                ->whereBetween('occurred_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
                ->sum('amount');

            if (($spentToday + $amount) > $dailyLimit) {
                throw new RuntimeException('Nominal transaksi melebihi batas harian yang diizinkan.');
            }
        }

        if ($weeklyLimit > 0) {
            $spentWeek = WalletTransaction::query()
                ->where('santri_id', $santri->id)
                ->where('type', 'debit')
                ->where('status', 'completed')
                ->whereBetween('occurred_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])
                ->sum('amount');

            if (($spentWeek + $amount) > $weeklyLimit) {
                throw new RuntimeException('Nominal transaksi melebihi batas mingguan yang diizinkan.');
            }
        }

        if ($monthlyLimit > 0) {
            $spentMonth = WalletTransaction::query()
                ->where('santri_id', $santri->id)
                ->where('type', 'debit')
                ->where('status', 'completed')
                ->whereBetween('occurred_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
                ->sum('amount');

            if (($spentMonth + $amount) > $monthlyLimit) {
                throw new RuntimeException('Nominal transaksi melebihi batas bulanan yang diizinkan.');
            }
        }
    }
}
