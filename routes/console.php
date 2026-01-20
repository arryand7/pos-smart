<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Transaction;
use App\Services\Accounting\AccountingService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('smart:fix-transaction-statuses {--apply : Terapkan perubahan} {--reverse-ledger : Balik jurnal jika status diturunkan} {--chunk=200 : Ukuran batch}', function () {
    $apply = (bool) $this->option('apply');
    $reverseLedger = (bool) $this->option('reverse-ledger');
    $chunk = (int) ($this->option('chunk') ?: 200);

    $stats = [
        'checked' => 0,
        'updated' => 0,
        'pending' => 0,
        'completed' => 0,
        'cancelled' => 0,
        'ledger_reversed' => 0,
    ];

    $accounting = app(AccountingService::class);

    $query = Transaction::query()
        ->where('gateway_amount', '>', 0);

    $query->chunkById($chunk, function ($transactions) use (&$stats, $apply, $reverseLedger, $accounting) {
        foreach ($transactions as $transaction) {
            $stats['checked']++;
            $transaction->loadMissing('payments');

            $oldStatus = $transaction->status;
            $newStatus = $oldStatus;

            $payments = $transaction->payments;
            $settled = $payments->first(fn ($payment) => in_array(strtolower((string) $payment->status), [
                'settlement', 'capture', 'completed', 'paid', 'success',
            ], true));
            $cancelled = $payments->first(fn ($payment) => in_array(strtolower((string) $payment->status), [
                'cancelled', 'cancel', 'expired', 'expire', 'deny', 'failed',
            ], true));

            if ($settled) {
                $newStatus = 'completed';
            } elseif ($cancelled) {
                $newStatus = 'cancelled';
            } else {
                $newStatus = 'pending';
            }

            if ($newStatus !== $oldStatus) {
                $stats[$newStatus]++;
                $stats['updated']++;

                $paidAmount = $newStatus === 'completed'
                    ? $transaction->cash_amount + $transaction->wallet_amount + $transaction->gateway_amount
                    : $transaction->cash_amount + $transaction->wallet_amount;

                $transaction->paid_amount = $paidAmount;
                $transaction->change_amount = max(0, $paidAmount - $transaction->total_amount);
                $transaction->status = $newStatus;

                $this->line(sprintf(
                    '%s: %s -> %s',
                    $transaction->reference,
                    strtoupper((string) $oldStatus),
                    strtoupper($newStatus)
                ));

                if ($apply) {
                    $transaction->save();

                    if ($reverseLedger && $oldStatus === 'completed' && $newStatus !== 'completed') {
                        $reversed = $accounting->reversePosTransaction($transaction, 'Perbaikan status transaksi lama');
                        if ($reversed) {
                            $stats['ledger_reversed']++;
                        }
                    }
                }
            }
        }
    });

    $this->newLine();
    $this->info('Ringkasan:');
    $this->line("Dicek: {$stats['checked']}");
    $this->line("Diubah: {$stats['updated']}");
    $this->line("Menjadi Pending: {$stats['pending']}");
    $this->line("Menjadi Completed: {$stats['completed']}");
    $this->line("Menjadi Cancelled: {$stats['cancelled']}");
    if ($reverseLedger) {
        $this->line("Jurnal dibalik: {$stats['ledger_reversed']}");
    }

    if (! $apply) {
        $this->warn('Mode dry-run. Jalankan dengan --apply untuk menerapkan.');
    }
})->purpose('Perbaiki status transaksi gateway lama agar sesuai status pembayaran.');
