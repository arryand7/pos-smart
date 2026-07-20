<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\JournalEntry;
use App\Models\Santri;
use App\Models\Transaction;
use App\Support\MoneyColumnAuditor;
use App\Support\MoneyValueException;
use App\Support\Rupiah;
use App\Support\WalletLedgerReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PosWalletPreflight extends Command
{
    protected $signature = 'smart:pos-wallet-preflight';

    protected $description = 'Production preflight read-only untuk integritas POS, wallet, inventory, dan jurnal';

    public function handle(MoneyColumnAuditor $moneyAuditor, WalletLedgerReconciler $reconciler): int
    {
        $checks = [];
        $add = function (string $check, int $count, string $severity = 'CRITICAL') use (&$checks): void {
            $checks[] = [$severity, $check, $count, $count === 0 ? 'OK' : 'FAILED'];
        };

        if (! Schema::hasColumn('transactions', 'client_transaction_id')) {
            $add('Migration client_transaction_id belum diterapkan', 1);
        } else {
            $add('Duplicate client_transaction_id', $this->duplicateGroups('transactions', ['client_transaction_id'], ['client_transaction_id']));
            $add('Completed transaction dengan client_transaction_id null', DB::table('transactions')->where('status', 'completed')->whereNull('client_transaction_id')->count());
        }

        if (! Schema::hasColumn('wallet_transactions', 'idempotency_key')) {
            $add('Migration wallet idempotency belum diterapkan', 1);
        } else {
            $add('Duplicate wallet idempotency key', $this->duplicateGroups('wallet_transactions', ['idempotency_key'], ['idempotency_key']));
            $add('POS wallet debit tanpa UUID/idempotency key', DB::table('wallet_transactions')
                ->where('reference_type', Transaction::class)->where('type', 'debit')
                ->where(fn ($q) => $q->whereNull('uuid')->orWhereNull('idempotency_key'))->count());
        }

        if (! Schema::hasColumn('journal_entries', 'journal_type')) {
            $add('Migration journal_type belum diterapkan', 1);
            $legacyDuplicates = DB::table('journal_entries')
                ->whereNotNull('source_type')->whereNotNull('source_id')
                ->get(['source_type', 'source_id', 'reference'])
                ->groupBy(fn ($entry) => implode('|', [
                    $entry->source_type,
                    $entry->source_id,
                    str_starts_with((string) $entry->reference, 'REV-') ? 'reversal' : 'primary',
                ]))
                ->filter(fn ($entries) => $entries->count() > 1)
                ->count();
            $add('Duplicate legacy journal source yang akan menggagalkan migration', $legacyDuplicates);
        } else {
            $add('Duplicate journal source', $this->duplicateGroups(
                'journal_entries', ['source_type', 'source_id', 'journal_type'], ['source_type', 'source_id'],
            ));
        }

        $completed = DB::table('transactions')->where('status', 'completed')->where('type', 'pos');
        $add('Completed wallet transaction tanpa wallet debit', (clone $completed)->where('wallet_amount', '>', 0)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('wallet_transactions')
                ->whereColumn('wallet_transactions.reference_id', 'transactions.id')
                ->where('wallet_transactions.reference_type', Transaction::class)->where('wallet_transactions.type', 'debit'))->count());
        $add('Completed transaction tanpa journal primary', (clone $completed)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('journal_entries')
                ->whereColumn('journal_entries.source_id', 'transactions.id')
                ->where('journal_entries.source_type', Transaction::class)
                ->when(Schema::hasColumn('journal_entries', 'journal_type'), fn ($q) => $q->where('journal_entries.journal_type', 'primary')))->count());
        $add('Completed transaction tanpa transaction items', (clone $completed)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('transaction_items')->whereColumn('transaction_items.transaction_id', 'transactions.id'))->count());
        $add('Completed transaction tanpa inventory movement', (clone $completed)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('inventory_movements')
                ->whereColumn('inventory_movements.reference_id', 'transactions.id')
                ->where('inventory_movements.reference_type', Transaction::class)->where('inventory_movements.type', 'sale'))->count());

        $add('Wallet ledger dengan transaction reference tidak valid', DB::table('wallet_transactions')
            ->where('reference_type', Transaction::class)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('transactions')
                ->whereColumn('transactions.id', 'wallet_transactions.reference_id'))->count());

        $walletMismatch = 0;
        Santri::query()->orderBy('id')->chunkById(200, function ($santris) use (&$walletMismatch, $reconciler) {
            foreach ($santris as $santri) {
                try {
                    $cached = Rupiah::from($santri->wallet_balance, 'wallet_balance');
                    $result = $reconciler->reconcile($santri);
                    if ($cached !== $result['calculated'] || ! $result['chain_valid']) {
                        $walletMismatch++;
                    }
                } catch (MoneyValueException) {
                    $walletMismatch++;
                }
            }
        });
        $add('Cached wallet balance berbeda dari ledger', $walletMismatch);
        $add('Stok negatif', DB::table('products')->where('stock', '<', 0)->count());
        $add('Nominal dengan pecahan/format tidak valid', count($moneyAuditor->audit()));
        $add('Kasir tanpa assignment lokasi', Schema::hasColumn('users', 'location_id')
            ? DB::table('users')->where('role', UserRole::KASIR->value)->whereNull('location_id')->count()
            : 1);
        $accountCodes = array_filter([
            config('smart.accounting.accounts.cash'),
            config('smart.accounting.accounts.wallet_liability'),
            config('smart.accounting.accounts.revenue'),
        ]);
        $configuredAccounts = Schema::hasTable('accounts')
            ? DB::table('accounts')->whereIn('code', $accountCodes)->where('is_active', true)->count()
            : 0;
        $add('Akun jurnal POS belum tersedia/aktif', max(0, count(array_unique($accountCodes)) - $configuredAccounts));
        $add('Journal entry tidak seimbang', $this->unbalancedJournals());

        $this->info('SMART POS & Wallet Production Preflight (read-only)');
        $this->table(['Severity', 'Check', 'Count', 'Status'], $checks);
        $critical = collect($checks)->where(0, 'CRITICAL')->sum(2);
        $this->line('Critical findings: '.$critical.'. Tidak ada data yang diubah.');

        return $critical === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function duplicateGroups(string $table, array $groupColumns, array $notNullColumns): int
    {
        $query = DB::table($table);
        foreach ($notNullColumns as $column) {
            $query->whereNotNull($column);
        }

        return DB::query()->fromSub(
            $query->select($groupColumns)->groupBy($groupColumns)->havingRaw('COUNT(*) > 1'),
            'duplicates',
        )->count();
    }

    private function unbalancedJournals(): int
    {
        $unbalanced = 0;
        JournalEntry::query()->with('lines')->orderBy('id')->chunkById(200, function ($entries) use (&$unbalanced) {
            foreach ($entries as $entry) {
                try {
                    $headerDebit = Rupiah::from($entry->total_debit, 'journal.total_debit');
                    $headerCredit = Rupiah::from($entry->total_credit, 'journal.total_credit');
                    $lineDebit = 0;
                    $lineCredit = 0;
                    foreach ($entry->lines as $line) {
                        $amount = Rupiah::from($line->amount, 'journal_line.amount');
                        if ($line->type === 'debit') {
                            $lineDebit += $amount;
                        } else {
                            $lineCredit += $amount;
                        }
                    }
                    if ($headerDebit !== $headerCredit || $lineDebit !== $lineCredit
                        || $headerDebit !== $lineDebit || $headerCredit !== $lineCredit) {
                        $unbalanced++;
                    }
                } catch (MoneyValueException) {
                    $unbalanced++;
                }
            }
        });

        return $unbalanced;
    }
}
