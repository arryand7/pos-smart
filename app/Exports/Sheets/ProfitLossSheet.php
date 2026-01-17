<?php

namespace App\Exports\Sheets;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProfitLossSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected array $data,
        protected Carbon $start,
        protected Carbon $end,
    ) {}

    public function title(): string
    {
        return 'Laba Rugi';
    }

    public function headings(): array
    {
        return [
            ['Laporan Laba/Rugi'],
            ['Periode: ' . $this->start->format('d M Y') . ' - ' . $this->end->format('d M Y')],
            [],
            ['Kode', 'Nama Akun', 'Tipe', 'Jumlah'],
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->data['revenues'] as $account) {
            $rows[] = [$account->code, $account->name, 'Pendapatan', $account->balance];
        }

        $rows[] = ['', 'Total Pendapatan', '', $this->data['totalRevenue']];
        $rows[] = [];

        foreach ($this->data['expenses'] as $account) {
            $rows[] = [$account->code, $account->name, 'Beban', $account->balance];
        }

        $rows[] = ['', 'Total Beban', '', $this->data['totalExpense']];
        $rows[] = [];
        $rows[] = ['', 'LABA/RUGI BERSIH', '', $this->data['netIncome']];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            4 => ['font' => ['bold' => true]],
        ];
    }
}
