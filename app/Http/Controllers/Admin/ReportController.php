<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Exports\ExportsTable;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\WalletTransaction;
use App\Services\POS\PosService;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    use ExportsTable;
    public function index(): View
    {
        return view('admin.reports.index');
    }

    public function transactionJournal(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        $query = Transaction::with(['location', 'kasir', 'santri'])
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhereHas('santri', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('nis', 'like', "%{$search}%"))
                    ->orWhereHas('kasir', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        $sort = $request->string('sort')->value();
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';

        $query->when($sort, function ($builder) use ($sort, $direction) {
            return match ($sort) {
                'reference', 'created_at', 'total_amount', 'status', 'type' => $builder->orderBy($sort, $direction),
                'santri' => $builder->orderBy(
                    \App\Models\Santri::select('name')->whereColumn('santris.id', 'transactions.santri_id'),
                    $direction
                ),
                'kasir' => $builder->orderBy(
                    \App\Models\User::select('name')->whereColumn('users.id', 'transactions.kasir_id'),
                    $direction
                ),
                default => $builder->orderByDesc('created_at'),
            };
        }, fn ($builder) => $builder->orderByDesc('created_at'));

        if ($exportType = $this->exportType($request)) {
            $rows = $query->get()->map(function (Transaction $trx) {
                $santriLabel = $trx->santri ? "{$trx->santri->name} ({$trx->santri->nis})" : '-';

                return [
                    $trx->reference,
                    $trx->created_at?->format('d/m/Y H:i') ?? '-',
                    $santriLabel,
                    $trx->kasir->name ?? '-',
                    strtoupper($trx->type),
                    ucfirst($trx->channel),
                    number_format($trx->total_amount, 0, ',', '.'),
                    strtoupper($trx->status),
                ];
            })->all();

            $headings = ['REF ID', 'Waktu', 'Santri', 'Kasir', 'Sales Type', 'Channel', 'Total', 'Status'];

            return $this->exportTable($exportType, 'transaksi', $headings, $rows);
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        $transactions = $query->paginate($perPage)->withQueryString();

        return view('admin.reports.transaction-journal', compact('transactions', 'startDate', 'endDate'));
    }

    public function showTransaction(Transaction $transaction): View
    {
        $transaction->load(['items', 'location', 'kasir', 'santri', 'payments']);

        $metadata = $transaction->metadata ?? [];
        if (empty($metadata['verification_token'])) {
            $metadata['verification_token'] = Str::uuid()->toString();
            $transaction->metadata = $metadata;
            $transaction->save();
        }

        $verificationUrl = route('transactions.verify', ['token' => $metadata['verification_token']]);

        return view('admin.reports.transaction-invoice', compact('transaction', 'verificationUrl'));
    }

    public function receipt(Transaction $transaction, Request $request): View
    {
        $transaction->load(['items', 'location', 'kasir', 'santri', 'payments']);
        $size = $request->string('size', '80')->toString();
        $size = in_array($size, ['58', '80'], true) ? $size : '80';

        $metadata = $transaction->metadata ?? [];
        if (empty($metadata['verification_token'])) {
            $metadata['verification_token'] = Str::uuid()->toString();
            $transaction->metadata = $metadata;
            $transaction->save();
        }

        $verificationUrl = route('transactions.verify', ['token' => $metadata['verification_token']]);

        return view('admin.reports.transaction-receipt', compact('transaction', 'size', 'verificationUrl'));
    }

    public function receiptPdf(Transaction $transaction, Request $request)
    {
        $transaction->load(['items', 'location', 'kasir', 'santri', 'payments']);
        $size = $request->string('size', '80')->toString();
        $size = in_array($size, ['58', '80'], true) ? $size : '80';

        $metadata = $transaction->metadata ?? [];
        if (empty($metadata['verification_token'])) {
            $metadata['verification_token'] = Str::uuid()->toString();
            $transaction->metadata = $metadata;
            $transaction->save();
        }

        $verificationUrl = route('transactions.verify', ['token' => $metadata['verification_token']]);

        $qrDataUri = Builder::create()
            ->data($verificationUrl)
            ->size(140)
            ->margin(1)
            ->build()
            ->getDataUri();

        $barcodeGenerator = new BarcodeGeneratorPNG();
        $barcodeDataUri = 'data:image/png;base64,'.base64_encode(
            $barcodeGenerator->getBarcode($transaction->reference, $barcodeGenerator::TYPE_CODE_128)
        );

        $paperWidth = $size === '58' ? 58 : 80;
        $paperHeight = max(200, 80 + ($transaction->items->count() * 6));
        $points = fn ($mm) => $mm * 2.83465;

        $pdf = Pdf::loadView('admin.reports.transaction-receipt-pdf', [
            'transaction' => $transaction,
            'size' => $size,
            'verificationUrl' => $verificationUrl,
            'qrDataUri' => $qrDataUri,
            'barcodeDataUri' => $barcodeDataUri,
        ])->setPaper([0, 0, $points($paperWidth), $points($paperHeight)]);

        return $pdf->stream("struk-{$transaction->reference}.pdf");
    }

    public function invoicePdf(Transaction $transaction)
    {
        $transaction->load(['items', 'location', 'kasir', 'santri', 'payments']);

        $metadata = $transaction->metadata ?? [];
        if (empty($metadata['verification_token'])) {
            $metadata['verification_token'] = Str::uuid()->toString();
            $transaction->metadata = $metadata;
            $transaction->save();
        }

        $verificationUrl = route('transactions.verify', ['token' => $metadata['verification_token']]);

        $qrDataUri = Builder::create()
            ->data($verificationUrl)
            ->size(140)
            ->margin(1)
            ->build()
            ->getDataUri();

        $barcodeGenerator = new BarcodeGeneratorPNG();
        $barcodeDataUri = 'data:image/png;base64,'.base64_encode(
            $barcodeGenerator->getBarcode($transaction->reference, $barcodeGenerator::TYPE_CODE_128)
        );

        $pdf = Pdf::loadView('admin.reports.transaction-invoice-pdf', [
            'transaction' => $transaction,
            'verificationUrl' => $verificationUrl,
            'qrDataUri' => $qrDataUri,
            'barcodeDataUri' => $barcodeDataUri,
        ])->setPaper('a4');

        return $pdf->stream("invoice-{$transaction->reference}.pdf");
    }

    public function salesReport(Request $request): View
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        // Top Products
        $topProducts = TransactionItem::select(
                'product_name', 
                DB::raw('SUM(quantity) as total_qty'), 
                DB::raw('SUM(subtotal) as total_revenue')
            )
            ->whereHas('transaction', function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(), 
                    Carbon::parse($endDate)->endOfDay()
                ])->where('status', 'completed');
            })
            ->groupBy('product_name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        // Daily Sales Chart Data
        $dailySales = Transaction::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as total')
            )
            ->where('status', 'completed')
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(), 
                Carbon::parse($endDate)->endOfDay()
            ])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin.reports.sales', compact('topProducts', 'dailySales', 'startDate', 'endDate'));
    }

    public function walletReport(Request $request): View
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        $transactions = WalletTransaction::with(['santri', 'performer'])
            ->whereBetween('occurred_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->latest()
            ->get();
            
        // Summary
        $summary = [
            'deposit' => $transactions->where('type', 'credit')->sum('amount'),
            'usage' => $transactions->where('type', 'debit')->sum('amount'),
            'net' => $transactions->where('type', 'credit')->sum('amount') - $transactions->where('type', 'debit')->sum('amount'),
        ];

        return view('admin.reports.wallet', compact('transactions', 'summary', 'startDate', 'endDate'));
    }

    public function cancelTransaction(Request $request, Transaction $transaction, PosService $posService): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(\App\Enums\UserRole::SUPER_ADMIN)) {
            abort(403, 'Hanya super admin yang bisa membatalkan transaksi.');
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $posService->cancelTransaction($transaction, $user, $data['reason'] ?? null);

        return back()->with('status', 'Transaksi berhasil dibatalkan.');
    }
}
