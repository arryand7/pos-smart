<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterval;
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

    public function revenueSeries(Request $request): JsonResponse
    {
        $frame = $request->string('frame', '1d')->toString();
        $now = now();

        $config = $this->frameConfig($frame, $now);
        $frame = $config['frame'];

        $start = $config['start'];
        $interval = $config['interval'];
        $labelFormat = $config['label_format'];

        $labels = [];
        $data = [];
        $index = [];

        $cursor = $start->copy();
        while ($cursor <= $now) {
            $key = $cursor->format('Y-m-d H:i');
            $index[$key] = count($data);
            $labels[] = $cursor->translatedFormat($labelFormat);
            $data[] = 0.0;
            $cursor->add($interval);
        }

        $transactions = Transaction::query()
            ->where('status', 'completed')
            ->where(function ($query) use ($start, $now) {
                $query->whereBetween('processed_at', [$start, $now])
                    ->orWhere(function ($sub) use ($start, $now) {
                        $sub->whereNull('processed_at')
                            ->whereBetween('created_at', [$start, $now]);
                    });
            })
            ->get(['processed_at', 'created_at', 'total_amount']);

        foreach ($transactions as $transaction) {
            $timestamp = $transaction->processed_at ?? $transaction->created_at;
            if (! $timestamp) {
                continue;
            }
            $bucket = $this->bucketTimestamp(Carbon::parse($timestamp), $frame);
            $key = $bucket->format('Y-m-d H:i');
            if (isset($index[$key])) {
                $data[$index[$key]] += (float) $transaction->total_amount;
            }
        }

        $maShort = $this->movingAverage($data, $config['ma_short']);
        $maLong = $this->movingAverage($data, $config['ma_long']);

        return response()->json([
            'frame' => $frame,
            'labels' => $labels,
            'data' => $data,
            'ma_short' => $maShort,
            'ma_long' => $maLong,
            'meta' => [
                'ma_short' => $config['ma_short'],
                'ma_long' => $config['ma_long'],
                'total' => array_sum($data),
            ],
        ]);
    }

    protected function frameConfig(string $frame, Carbon $now): array
    {
        $frame = strtolower($frame);

        return match ($frame) {
            '1h' => [
                'frame' => '1h',
                'start' => $now->copy()->subHours(24)->startOfHour(),
                'interval' => CarbonInterval::hour(),
                'label_format' => 'H:i',
                'ma_short' => 6,
                'ma_long' => 18,
            ],
            '4h' => [
                'frame' => '4h',
                'start' => $now->copy()->subDays(7)->startOfHour(),
                'interval' => CarbonInterval::hours(4),
                'label_format' => 'd M H:i',
                'ma_short' => 6,
                'ma_long' => 18,
            ],
            '1m' => [
                'frame' => '1m',
                'start' => $now->copy()->subMonths(12)->startOfMonth(),
                'interval' => CarbonInterval::month(),
                'label_format' => 'M Y',
                'ma_short' => 3,
                'ma_long' => 6,
            ],
            '3m' => [
                'frame' => '3m',
                'start' => $now->copy()->subMonths(36)->startOfMonth(),
                'interval' => CarbonInterval::months(3),
                'label_format' => 'M Y',
                'ma_short' => 2,
                'ma_long' => 4,
            ],
            '6m' => [
                'frame' => '6m',
                'start' => $now->copy()->subMonths(60)->startOfMonth(),
                'interval' => CarbonInterval::months(6),
                'label_format' => 'M Y',
                'ma_short' => 2,
                'ma_long' => 4,
            ],
            '1y' => [
                'frame' => '1y',
                'start' => $now->copy()->subYears(5)->startOfYear(),
                'interval' => CarbonInterval::year(),
                'label_format' => 'Y',
                'ma_short' => 2,
                'ma_long' => 3,
            ],
            '3y' => [
                'frame' => '3y',
                'start' => $now->copy()->subYears(15)->startOfYear(),
                'interval' => CarbonInterval::years(3),
                'label_format' => 'Y',
                'ma_short' => 2,
                'ma_long' => 3,
            ],
            '5y' => [
                'frame' => '5y',
                'start' => $now->copy()->subYears(25)->startOfYear(),
                'interval' => CarbonInterval::years(5),
                'label_format' => 'Y',
                'ma_short' => 2,
                'ma_long' => 3,
            ],
            default => [
                'frame' => '1d',
                'start' => $now->copy()->subDays(30)->startOfDay(),
                'interval' => CarbonInterval::day(),
                'label_format' => 'd M',
                'ma_short' => 7,
                'ma_long' => 21,
            ],
        };
    }

    protected function bucketTimestamp(Carbon $timestamp, string $frame): Carbon
    {
        return match ($frame) {
            '1h' => $timestamp->copy()->minute(0)->second(0),
            '4h' => $timestamp->copy()->minute(0)->second(0)->hour((int) (floor($timestamp->hour / 4) * 4)),
            '1m' => $timestamp->copy()->startOfMonth(),
            '3m' => $timestamp->copy()->startOfMonth()->month(((int) (floor(($timestamp->month - 1) / 3) * 3) + 1)),
            '6m' => $timestamp->copy()->startOfMonth()->month($timestamp->month <= 6 ? 1 : 7),
            '1y' => $timestamp->copy()->startOfYear(),
            '3y' => $timestamp->copy()->startOfYear()->year($timestamp->year - (($timestamp->year - 1) % 3)),
            '5y' => $timestamp->copy()->startOfYear()->year($timestamp->year - (($timestamp->year - 1) % 5)),
            default => $timestamp->copy()->startOfDay(),
        };
    }

    protected function movingAverage(array $data, int $window): array
    {
        $result = [];
        $sum = 0.0;
        $queue = [];

        foreach ($data as $index => $value) {
            $queue[] = $value;
            $sum += $value;

            if (count($queue) > $window) {
                $sum -= array_shift($queue);
            }

            if ($index + 1 < $window) {
                $result[] = null;
            } else {
                $result[] = round($sum / $window, 2);
            }
        }

        return $result;
    }
}
