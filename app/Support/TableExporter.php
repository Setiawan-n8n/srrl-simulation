<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared "Export Excel" / "Export PDF" support for Filament list pages.
 *
 * Both methods take the same plain-array shape: a list of column headers
 * and an iterable of rows (each row an array of values in the same order
 * as the headers), so a page's export action only has to build that data
 * once and can hand it to either method.
 */
class TableExporter
{
    /**
     * Stream an .xlsx download.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function excel(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray($headers, null, 'A1');

        $rowNum = 2;
        foreach ($rows as $row) {
            $sheet->fromArray(array_values($row), null, 'A'.$rowNum);
            $rowNum++;
        }

        $lastCol = Coordinate::stringFromColumnIndex(max(count($headers), 1));
        $headerRange = "A1:{$lastCol}1";

        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FDE9C8');

        for ($col = 1; $col <= count($headers); $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        if ($rowNum > 2) {
            $sheet->freezePane('A2');
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Stream a PDF download built with the dependency-free SimplePdfWriter.
     *
     * Must return a StreamedResponse (not a plain Response) — Livewire's
     * file-download support (Features/SupportFileDownloads) only recognizes
     * StreamedResponse/BinaryFileResponse return values from an action.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     * @param  array<int, float>|null  $colWeights  Relative column width weights.
     */
    public static function pdf(string $filename, string $title, array $headers, iterable $rows, ?array $colWeights = null): StreamedResponse
    {
        $pdf = SimplePdfWriter::table($title, $headers, $rows, $colWeights);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename.'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
