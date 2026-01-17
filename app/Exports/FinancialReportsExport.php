<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FinancialReportsExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected array $profitLoss,
        protected array $balanceSheet,
        protected array $cashFlow,
        protected Carbon $start,
        protected Carbon $end,
        protected Carbon $asOf,
    ) {}

    public function sheets(): array
    {
        return [
            'Laba Rugi' => new Sheets\ProfitLossSheet($this->profitLoss, $this->start, $this->end),
            'Neraca' => new Sheets\BalanceSheetSheet($this->balanceSheet, $this->asOf),
            'Arus Kas' => new Sheets\CashFlowSheet($this->cashFlow, $this->start, $this->end),
        ];
    }
}
