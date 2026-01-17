<?php

namespace App\Exports\Sheets;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CashFlowSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected array $data,
        protected Carbon $start,
        protected Carbon $end,
    ) {}

    public function title(): string
    {
        return 'Arus Kas';
    }

    public function headings(): array
    {
        return [
            ['Laporan Arus Kas'],
            ['Periode: ' . $this->start->format('d M Y') . ' - ' . $this->end->format('d M Y')],
            [],
            ['Tanggal', 'Referensi', 'Keterangan', 'Jumlah'],
        ];
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['--- ARUS KAS MASUK ---', '', '', ''];
        foreach ($this->data['inflows'] as $line) {
            $rows[] = [
                Carbon::parse($line->entry_date)->format('d/m/Y'),
                $line->reference ?? '-',
                $line->description ?? '-',
                $line->net_amount,
            ];
        }
        $rows[] = ['', '', 'Total Kas Masuk', $this->data['totalIn']];
        $rows[] = [];

        $rows[] = ['--- ARUS KAS KELUAR ---', '', '', ''];
        foreach ($this->data['outflows'] as $line) {
            $rows[] = [
                Carbon::parse($line->entry_date)->format('d/m/Y'),
                $line->reference ?? '-',
                $line->description ?? '-',
                abs($line->net_amount),
            ];
        }
        $rows[] = ['', '', 'Total Kas Keluar', $this->data['totalOut']];
        $rows[] = [];

        $rows[] = ['', '', 'Saldo Awal Kas', $this->data['openingBalance']];
        $rows[] = ['', '', 'Arus Kas Bersih', $this->data['netCash']];
        $rows[] = ['', '', 'SALDO AKHIR KAS', $this->data['closingBalance']];

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
