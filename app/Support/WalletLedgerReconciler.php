<?php

namespace App\Support;

use App\Models\Santri;
use App\Models\WalletTransaction;

class WalletLedgerReconciler
{
    /** @return array{calculated:int,last:?WalletTransaction,chain_valid:bool} */
    public function reconcile(Santri $santri): array
    {
        $entries = $santri->walletTransactions()->orderBy('occurred_at')->orderBy('id')->get();
        if ($entries->isEmpty()) {
            return ['calculated' => 0, 'last' => null, 'chain_valid' => true];
        }

        try {
            $running = Rupiah::from($entries->first()->balance_before, 'wallet balance_before');
            $valid = true;
            foreach ($entries as $entry) {
                $before = Rupiah::from($entry->balance_before, 'wallet balance_before');
                $after = Rupiah::from($entry->balance_after, 'wallet balance_after');
                $amount = Rupiah::from($entry->amount, 'wallet amount');
                $valid = $valid && $before === $running;
                $expected = match ($entry->type) {
                    'credit' => $running + $amount,
                    'debit' => $running - $amount,
                    default => $after,
                };
                $valid = $valid && $expected === $after;
                $running = $after;
            }

            return ['calculated' => $running, 'last' => $entries->last(), 'chain_valid' => $valid];
        } catch (MoneyValueException) {
            return ['calculated' => 0, 'last' => $entries->last(), 'chain_valid' => false];
        }
    }
}
