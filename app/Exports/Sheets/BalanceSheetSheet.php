<?php

namespace App\Exports\Sheets;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BalanceSheetSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected array $data,
        protected Carbon $asOf,
    ) {}

    public function title(): string
    {
        return 'Neraca';
    }

    public function headings(): array
    {
        return [
            ['Laporan Neraca'],
            ['Per tanggal: ' . $this->asOf->format('d M Y')],
            [],
            ['Kode', 'Nama Akun', 'Tipe', 'Jumlah'],
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->data['assets'] as $account) {
            $rows[] = [$account->code, $account->name, 'Aset', $account->balance];
        }
        $rows[] = ['', 'Total Aset', '', $this->data['totalAssets']];
        $rows[] = [];

        foreach ($this->data['liabilities'] as $account) {
            $rows[] = [$account->code, $account->name, 'Liabilitas', $account->balance];
        }
        $rows[] = ['', 'Total Liabilitas', '', $this->data['totalLiabilities']];
        $rows[] = [];

        foreach ($this->data['equities'] as $account) {
            $rows[] = [$account->code, $account->name, 'Ekuitas', $account->balance];
        }
        $rows[] = ['', 'Total Ekuitas', '', $this->data['totalEquities']];
        $rows[] = [];
        $rows[] = ['', 'TOTAL LIABILITAS + EKUITAS', '', $this->data['totalLiabilities'] + $this->data['totalEquities']];

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
