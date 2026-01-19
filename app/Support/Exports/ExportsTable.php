<?php

namespace App\Support\Exports;

use App\Exports\TableExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;

trait ExportsTable
{
    protected function exportTable(string $type, string $filename, array $headings, array $rows)
    {
        $type = strtolower($type);

        if (in_array($type, ['xlsx', 'excel'], true)) {
            return Excel::download(new TableExport($headings, $rows), "{$filename}.xlsx", ExcelWriter::XLSX);
        }

        if ($type === 'csv') {
            return Excel::download(new TableExport($headings, $rows), "{$filename}.csv", ExcelWriter::CSV);
        }

        if ($type === 'pdf') {
            $pdf = Pdf::loadView('exports.table-pdf', [
                'title' => $filename,
                'headings' => $headings,
                'rows' => $rows,
            ])->setPaper('a4', 'landscape');

            return $pdf->download("{$filename}.pdf");
        }

        return null;
    }

    protected function exportType(Request $request): ?string
    {
        $type = $request->string('export')->lower()->value();

        if (! $type) {
            return null;
        }

        return in_array($type, ['excel', 'xlsx', 'csv', 'pdf'], true) ? $type : null;
    }
}
