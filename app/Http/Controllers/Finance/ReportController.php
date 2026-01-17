<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function profitLoss(Request $request): View
    {
        [$start, $end] = $this->resolvePeriod($request);

        $lines = $this->aggregateByAccount(['revenue', 'expense'], $start, $end);
        $revenues = $lines->where('type', 'revenue')->values();
        $expenses = $lines->where('type', 'expense')->values();

        $totalRevenue = $revenues->sum('balance');
        $totalExpense = $expenses->sum('balance');
        $netIncome = $totalRevenue - $totalExpense;

        return view('reports.profit-loss', compact(
            'start',
            'end',
            'revenues',
            'expenses',
            'totalRevenue',
            'totalExpense',
            'netIncome'
        ));
    }

    public function balanceSheet(Request $request): View
    {
        $asOf = $this->resolveAsOf($request);

        $lines = $this->aggregateByAccount(['asset', 'liability', 'equity'], null, $asOf);
        $assets = $lines->where('type', 'asset')->values();
        $liabilities = $lines->where('type', 'liability')->values();
        $equities = $lines->where('type', 'equity')->values();

        $totalAssets = $assets->sum('balance');
        $totalLiabilities = $liabilities->sum('balance');
        $totalEquities = $equities->sum('balance');

        return view('reports.balance-sheet', compact(
            'asOf',
            'assets',
            'liabilities',
            'equities',
            'totalAssets',
            'totalLiabilities',
            'totalEquities'
        ));
    }

    public function cashFlow(Request $request): View
    {
        [$start, $end] = $this->resolvePeriod($request);

        $cashAccountCode = config('smart.accounting.accounts.cash');
        $cashAccount = $cashAccountCode ? Account::where('code', $cashAccountCode)->first() : null;
        $cashLines = collect();

        if ($cashAccount) {
            $cashLines = JournalLine::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_lines.account_id', $cashAccount->id)
                ->whereDate('journal_entries.entry_date', '>=', $start->toDateString())
                ->whereDate('journal_entries.entry_date', '<=', $end->toDateString())
                ->groupBy('journal_entries.entry_date', 'journal_entries.reference', 'journal_entries.description')
                ->orderBy('journal_entries.entry_date')
                ->select([
                    'journal_entries.entry_date',
                    'journal_entries.reference',
                    'journal_entries.description',
                    DB::raw("SUM(CASE WHEN journal_lines.type = 'debit' THEN journal_lines.amount ELSE -journal_lines.amount END) as net_amount"),
                ])
                ->get();
        }

        $inflows = $cashLines->filter(fn ($line) => $line->net_amount > 0)->values();
        $outflows = $cashLines->filter(fn ($line) => $line->net_amount < 0)->values();

        $totalIn = $inflows->sum('net_amount');
        $totalOut = $outflows->sum(fn ($line) => abs((float) $line->net_amount));
        $netCash = $totalIn - $totalOut;

        $openingBalance = $cashAccount ? $this->cashBalanceAsOf($cashAccount->id, $start->copy()->subDay()) : 0;
        $closingBalance = $cashAccount ? $openingBalance + $netCash : 0;

        return view('reports.cash-flow', compact(
            'start',
            'end',
            'cashAccount',
            'inflows',
            'outflows',
            'totalIn',
            'totalOut',
            'netCash',
            'openingBalance',
            'closingBalance'
        ));
    }

    protected function resolvePeriod(Request $request): array
    {
        $defaultStart = now()->startOfMonth();
        $defaultEnd = now();

        $start = $this->parseDate($request->query('start'), $defaultStart);
        $end = $this->parseDate($request->query('end'), $defaultEnd);

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy(), $start->copy()];
        }

        return [$start, $end];
    }

    protected function resolveAsOf(Request $request): Carbon
    {
        return $this->parseDate($request->query('as_of'), now());
    }

    protected function parseDate(?string $value, Carbon $fallback): Carbon
    {
        if (! $value) {
            return $fallback->copy();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable $throwable) {
            return $fallback->copy();
        }
    }

    protected function aggregateByAccount(array $types, ?Carbon $start, Carbon $end): Collection
    {
        $query = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('accounts.type', $types)
            ->whereDate('journal_entries.entry_date', '<=', $end->toDateString())
            ->select([
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw("SUM(CASE WHEN journal_lines.type = 'debit' THEN journal_lines.amount ELSE 0 END) as total_debit"),
                DB::raw("SUM(CASE WHEN journal_lines.type = 'credit' THEN journal_lines.amount ELSE 0 END) as total_credit"),
            ])
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code');

        if ($start) {
            $query->whereDate('journal_entries.entry_date', '>=', $start->toDateString());
        }

        return $query->get()->map(function ($row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;

            $row->balance = $this->normalBalance($row->type, $debit, $credit);

            return $row;
        });
    }

    protected function normalBalance(string $type, float $debit, float $credit): float
    {
        return match ($type) {
            'asset', 'expense' => $debit - $credit,
            default => $credit - $debit,
        };
    }

    protected function cashBalanceAsOf(int $accountId, Carbon $asOf): float
    {
        $balance = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $accountId)
            ->whereDate('journal_entries.entry_date', '<=', $asOf->toDateString())
            ->selectRaw("SUM(CASE WHEN journal_lines.type = 'debit' THEN journal_lines.amount ELSE -journal_lines.amount END) as balance")
            ->value('balance');

        return (float) ($balance ?? 0);
    }
}
