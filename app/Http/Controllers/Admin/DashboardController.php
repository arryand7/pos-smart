<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'active_products' => Product::where('is_active', true)->count(),
            'low_stock' => Product::whereColumn('stock', '<=', 'stock_alert')->where('stock_alert', '>', 0)->count(),
            'total_santri' => \App\Models\Santri::count(),
            'today_sales' => \App\Models\Transaction::whereDate('created_at', now())->sum('total_amount'),
            'today_transactions' => \App\Models\Transaction::whereDate('created_at', now())->count(),
        ];

        $lowStockProducts = Product::whereColumn('stock', '<=', 'stock_alert')
            ->where('stock_alert', '>', 0)
            ->with('location')
            ->orderBy('stock')
            ->limit(10)
            ->get();

        return view('dashboards.admin', compact('stats', 'lowStockProducts'));
    }
}
