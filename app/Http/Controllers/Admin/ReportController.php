<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('admin.reports.index');
    }

    public function transactionJournal(Request $request): View
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        $transactions = Transaction::with(['location', 'kasir', 'santri'])
            ->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ])
            ->latest()
            ->get();

        return view('admin.reports.transaction-journal', compact('transactions', 'startDate', 'endDate'));
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
}
