<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function salesWeekly(Request $request): JsonResponse
    {
        $endDate = now();
        $startDate = now()->subDays(6);

        $sales = Transaction::query()
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $labels = [];
        $data = [];
        $counts = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayLabel = now()->subDays($i)->format('D');
            $labels[] = $dayLabel;
            $data[] = (float) ($sales->get($date)?->total ?? 0);
            $counts[] = (int) ($sales->get($date)?->count ?? 0);
        }

        return response()->json([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Omzet (Rp)',
                    'data' => $data,
                    'backgroundColor' => 'rgba(0, 122, 92, 0.6)',
                    'borderColor' => '#007A5C',
                    'borderWidth' => 2,
                ],
            ],
            'summary' => [
                'total' => array_sum($data),
                'transactions' => array_sum($counts),
            ],
        ]);
    }

    public function cashFlowWeekly(Request $request): JsonResponse
    {
        $endDate = now();
        $startDate = now()->subDays(6);

        $walletData = WalletTransaction::query()
            ->whereBetween('occurred_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->selectRaw("DATE(occurred_at) as date, type, SUM(amount) as total")
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get();

        $grouped = $walletData->groupBy('date');

        $labels = [];
        $inflows = [];
        $outflows = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayLabel = now()->subDays($i)->format('D');
            $labels[] = $dayLabel;

            $dayData = $grouped->get($date, collect());
            $inflows[] = (float) $dayData->where('type', 'credit')->sum('total');
            $outflows[] = (float) $dayData->where('type', 'debit')->sum('total');
        }

        return response()->json([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Kas Masuk',
                    'data' => $inflows,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.6)',
                    'borderColor' => '#22c55e',
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Kas Keluar',
                    'data' => $outflows,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.6)',
                    'borderColor' => '#ef4444',
                    'borderWidth' => 2,
                ],
            ],
            'summary' => [
                'totalIn' => array_sum($inflows),
                'totalOut' => array_sum($outflows),
                'net' => array_sum($inflows) - array_sum($outflows),
            ],
        ]);
    }

    public function kpis(Request $request): JsonResponse
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $todaySales = Transaction::whereDate('created_at', $today)->sum('total_amount');
        $monthSales = Transaction::where('created_at', '>=', $thisMonth)->sum('total_amount');
        $todayTransactions = Transaction::whereDate('created_at', $today)->count();
        $activeWallets = WalletTransaction::where('occurred_at', '>=', now()->subDays(7))
            ->distinct('santri_id')
            ->count('santri_id');

        return response()->json([
            'today_sales' => $todaySales,
            'month_sales' => $monthSales,
            'today_transactions' => $todayTransactions,
            'active_wallets' => $activeWallets,
        ]);
    }
}
