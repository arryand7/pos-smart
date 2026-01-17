<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Santri;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $since = Carbon::now()->subDays(30);

        $sales = Transaction::where('status', 'completed')
            ->where('processed_at', '>=', $since)
            ->sum('total_amount');

        $walletLiability = Santri::sum('wallet_balance');
        $pendingPayments = Payment::whereIn('status', ['pending', 'initiated'])->count();

        $stockAlerts = Product::whereColumn('stock', '<=', 'stock_alert')
            ->orderBy('stock')
            ->limit(5)
            ->get();

        $topProducts = TransactionItem::select('product_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as revenue'))
            ->whereHas('transaction', fn ($q) => $q->where('status', 'completed')->where('processed_at', '>=', $since))
            ->groupBy('product_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $recentPayments = Payment::latest('created_at')->limit(5)->get();

        return view('dashboards.finance', compact('sales', 'walletLiability', 'pendingPayments', 'stockAlerts', 'topProducts', 'recentPayments'));
    }
}
