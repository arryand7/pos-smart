<?php

namespace App\Services\Wallet;

use App\Models\Santri;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use App\Support\MoneyValueException;
use App\Support\Rupiah;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletService
{
    public function credit(Santri $santri, int|string $amount, array $context = []): WalletTransaction
    {
        $amount = $this->money($amount, 'wallet credit');

        return DB::transaction(function () use ($santri, $amount, $context) {
            $santri = Santri::lockForUpdate()->findOrFail($santri->id);

            $before = $this->money($santri->wallet_balance, "santris.{$santri->id}.wallet_balance");
            $after = $before + $amount;

            $santri->update(['wallet_balance' => $after]);

            return $this->storeTransaction($santri, $amount, $before, $after, array_merge($context, [
                'type' => $context['type'] ?? 'credit',
            ]));
        });
    }

    public function debit(Santri $santri, int|string $amount, array $context = []): WalletTransaction
    {
        $amount = $this->money($amount, 'wallet debit');

        return DB::transaction(function () use ($santri, $amount, $context) {
            $santri = Santri::lockForUpdate()->findOrFail($santri->id);

            $this->assertSufficientBalance($santri, $amount);
            $this->assertWithinLimit($santri, $amount);

            $before = $this->money($santri->wallet_balance, "santris.{$santri->id}.wallet_balance");
            $after = $before - $amount;

            $santri->update(['wallet_balance' => $after]);

            return $this->storeTransaction($santri, $amount * -1, $before, $after, array_merge($context, [
                'type' => $context['type'] ?? 'debit',
            ]));
        });
    }

    protected function storeTransaction(Santri $santri, int $amount, int $before, int $after, array $context): WalletTransaction
    {
        /** @var WalletTransaction $transaction */
        $transaction = new WalletTransaction([
            'uuid' => $context['uuid'] ?? Str::uuid()->toString(),
            'idempotency_key' => $context['idempotency_key'] ?? null,
            'reversed_transaction_id' => $context['reversed_transaction_id'] ?? null,
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

    protected function assertSufficientBalance(Santri $santri, int $amount): void
    {
        if ($this->money($santri->wallet_balance, "santris.{$santri->id}.wallet_balance") < $amount) {
            throw new WalletException('INSUFFICIENT_WALLET_BALANCE', 'Saldo dompet santri tidak mencukupi.');
        }
    }

    protected function assertWithinLimit(Santri $santri, int $amount): void
    {
        if ($santri->is_wallet_locked) {
            throw new WalletException('WALLET_INACTIVE', 'Dompet santri sedang diblokir oleh wali.');
        }

        $dailyLimit = $this->limit($santri->daily_limit, config('smart.wallet.default_daily_limit', 0), 'daily_limit');
        $weeklyLimit = $this->limit($santri->weekly_limit, config('smart.wallet.default_weekly_limit', 200000), 'weekly_limit');
        $monthlyLimit = $this->limit($santri->monthly_limit, config('smart.wallet.default_monthly_limit', 0), 'monthly_limit');

        $now = CarbonImmutable::now(config('smart.wallet.limit_timezone', 'Asia/Jakarta'));

        if ($dailyLimit > 0) {
            $spentToday = $this->completedSpend($santri, $now->startOfDay(), $now->endOfDay());

            if (($spentToday + $amount) > $dailyLimit) {
                throw new WalletException('DAILY_LIMIT_EXCEEDED', 'Nominal transaksi melebihi batas harian yang diizinkan.');
            }
        }

        if ($weeklyLimit > 0) {
            $spentWeek = $this->completedSpend($santri, $now->startOfWeek(), $now->endOfWeek());

            if (($spentWeek + $amount) > $weeklyLimit) {
                throw new WalletException('WEEKLY_LIMIT_EXCEEDED', 'Nominal transaksi melebihi batas mingguan yang diizinkan.');
            }
        }

        if ($monthlyLimit > 0) {
            $spentMonth = $this->completedSpend($santri, $now->startOfMonth(), $now->endOfMonth());

            if (($spentMonth + $amount) > $monthlyLimit) {
                throw new WalletException('MONTHLY_LIMIT_EXCEEDED', 'Nominal transaksi melebihi batas bulanan yang diizinkan.');
            }
        }
    }

    private function completedSpend(Santri $santri, CarbonImmutable $from, CarbonImmutable $until): int
    {
        $storageTimezone = config('app.timezone', 'UTC');
        $from = $from->setTimezone($storageTimezone);
        $until = $until->setTimezone($storageTimezone);

        $amount = WalletTransaction::query()
            ->leftJoin('transactions', function ($join) {
                $join->on('transactions.id', '=', 'wallet_transactions.reference_id')
                    ->where('wallet_transactions.reference_type', '=', Transaction::class);
            })
            ->where('wallet_transactions.santri_id', $santri->id)
            ->where('wallet_transactions.type', 'debit')
            ->where('wallet_transactions.status', 'completed')
            ->whereBetween('wallet_transactions.occurred_at', [$from, $until])
            ->where(function ($query) {
                $query->whereNull('wallet_transactions.reference_type')
                    ->orWhere('wallet_transactions.reference_type', '!=', Transaction::class)
                    ->orWhere('transactions.status', 'completed');
            })
            ->lockForUpdate()
            ->sum('wallet_transactions.amount');

        return $this->money((string) $amount, 'completed wallet spend');
    }

    private function money(mixed $amount, string $field): int
    {
        try {
            $rupiah = Rupiah::from($amount, $field);
        } catch (MoneyValueException $exception) {
            throw new WalletException('INVALID_MONEY_VALUE', $exception->getMessage());
        }

        if ($rupiah === 0 && in_array($field, ['wallet credit', 'wallet debit'], true)) {
            throw new WalletException('INVALID_MONEY_VALUE', "Nilai {$field} harus lebih besar dari nol.");
        }

        return $rupiah;
    }

    private function limit(mixed $specific, mixed $default, string $field): int
    {
        if ($specific === null || $specific === '') {
            return $this->money($default, "default_{$field}");
        }

        $value = $this->money($specific, $field);

        return $value === 0 ? $this->money($default, "default_{$field}") : $value;
    }
}
