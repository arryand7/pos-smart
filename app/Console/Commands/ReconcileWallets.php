<?php

namespace App\Console\Commands;

use App\Models\Santri;
use App\Support\MoneyValueException;
use App\Support\Rupiah;
use App\Support\WalletLedgerReconciler;
use Illuminate\Console\Command;

class ReconcileWallets extends Command
{
    protected $signature = 'smart:reconcile-wallets {--santri= : Batasi ke ID santri}';

    protected $description = 'Rekonsiliasi read-only cached wallet balance terhadap immutable ledger';

    public function handle(WalletLedgerReconciler $reconciler): int
    {
        $rows = [];
        $inconsistent = 0;
        $query = Santri::query()->when($this->option('santri'), fn ($q, $id) => $q->whereKey($id));

        $query->orderBy('id')->chunkById(200, function ($santris) use (&$rows, &$inconsistent, $reconciler) {
            foreach ($santris as $santri) {
                $result = $reconciler->reconcile($santri);
                $calculated = $result['calculated'];
                $last = $result['last'];
                $chainValid = $result['chain_valid'];
                try {
                    $cached = Rupiah::from($santri->wallet_balance, "santris.{$santri->id}.wallet_balance");
                    $difference = $cached - $calculated;
                    $consistent = $difference === 0 && $chainValid;
                } catch (MoneyValueException) {
                    $cached = (string) $santri->wallet_balance;
                    $difference = 'invalid';
                    $consistent = false;
                }
                $inconsistent += $consistent ? 0 : 1;
                $rows[] = [$santri->id, $santri->name, $cached, $calculated, $difference,
                    $last?->uuid ?? $last?->id ?? '-', $consistent ? 'CONSISTENT' : 'INCONSISTENT'];
            }
        });

        $this->info('SMART Wallet Reconciliation (read-only)');
        $this->table(['Santri ID', 'Santri', 'Cached', 'Ledger', 'Difference', 'Last Ledger', 'Status'], $rows);
        $this->line('Checked: '.count($rows).'; inconsistent: '.$inconsistent.'. Tidak ada data yang diubah.');

        return $inconsistent === 0 ? self::SUCCESS : self::FAILURE;
    }
}
