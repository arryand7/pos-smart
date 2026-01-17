<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    public function profitLossPdf(Request $request)
    {
        [$start, $end] = $this->resolvePeriod($request);
        $data = $this->getProfitLossData($start, $end);

        $pdf = Pdf::loadView('reports.pdf.profit-loss-pdf', array_merge($data, compact('start', 'end')));

        return $pdf->download('laporan-laba-rugi-' . $start->format('Y-m-d') . '.pdf');
    }

    public function balanceSheetPdf(Request $request)
    {
        $asOf = $this->resolveAsOf($request);
        $data = $this->getBalanceSheetData($asOf);

        $pdf = Pdf::loadView('reports.pdf.balance-sheet-pdf', array_merge($data, compact('asOf')));

        return $pdf->download('laporan-neraca-' . $asOf->format('Y-m-d') . '.pdf');
    }

    public function cashFlowPdf(Request $request)
    {
        [$start, $end] = $this->resolvePeriod($request);
        $data = $this->getCashFlowData($start, $end);

        $pdf = Pdf::loadView('reports.pdf.cash-flow-pdf', array_merge($data, compact('start', 'end')));

        return $pdf->download('laporan-arus-kas-' . $start->format('Y-m-d') . '.pdf');
    }

    public function exportExcel(Request $request)
    {
        [$start, $end] = $this->resolvePeriod($request);
        $asOf = $this->resolveAsOf($request);

        $profitLoss = $this->getProfitLossData($start, $end);
        $balanceSheet = $this->getBalanceSheetData($asOf);
        $cashFlow = $this->getCashFlowData($start, $end);

        return Excel::download(
            new \App\Exports\FinancialReportsExport($profitLoss, $balanceSheet, $cashFlow, $start, $end, $asOf),
            'laporan-keuangan-' . now()->format('Y-m-d') . '.xlsx'
        );
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

    protected function getProfitLossData(Carbon $start, Carbon $end): array
    {
        $lines = $this->aggregateByAccount(['revenue', 'expense'], $start, $end);
        $revenues = $lines->where('type', 'revenue')->values();
        $expenses = $lines->where('type', 'expense')->values();

        $totalRevenue = $revenues->sum('balance');
        $totalExpense = $expenses->sum('balance');
        $netIncome = $totalRevenue - $totalExpense;

        return compact('revenues', 'expenses', 'totalRevenue', 'totalExpense', 'netIncome');
    }

    protected function getBalanceSheetData(Carbon $asOf): array
    {
        $lines = $this->aggregateByAccount(['asset', 'liability', 'equity'], null, $asOf);
        $assets = $lines->where('type', 'asset')->values();
        $liabilities = $lines->where('type', 'liability')->values();
        $equities = $lines->where('type', 'equity')->values();

        $totalAssets = $assets->sum('balance');
        $totalLiabilities = $liabilities->sum('balance');
        $totalEquities = $equities->sum('balance');

        return compact('assets', 'liabilities', 'equities', 'totalAssets', 'totalLiabilities', 'totalEquities');
    }

    protected function getCashFlowData(Carbon $start, Carbon $end): array
    {
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

        return compact('cashAccount', 'inflows', 'outflows', 'totalIn', 'totalOut', 'netCash', 'openingBalance', 'closingBalance');
    }

    protected function aggregateByAccount(array $types, ?Carbon $start, Carbon $end)
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
