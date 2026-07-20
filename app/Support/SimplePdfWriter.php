<?php

namespace App\Support;

/**
 * Minimal, dependency-free PDF table writer.
 *
 * No PDF rendering package (dompdf/mpdf/tcpdf) is installed in this project,
 * so this class writes a valid PDF 1.4 file by hand: a Catalog, a Pages
 * tree, one or more Page objects (auto-paginated), the two standard
 * Helvetica base-14 fonts (no font embedding needed), and a simple text
 * content stream per page. Output uses only ASCII/Latin-1 text operators,
 * which every PDF reader supports without extra resources.
 *
 * The exact object/xref/trailer layout below was prototyped and validated
 * (qpdf --check, pikepdf, pypdf text extraction) before being ported here.
 */
class SimplePdfWriter
{
    protected float $pageWidth = 841.89;  // A4 landscape, in points

    protected float $pageHeight = 595.28;

    protected float $margin = 36.0;

    protected float $titleHeight = 26.0;

    protected float $headerRowHeight = 18.0;

    protected float $rowHeight = 15.0;

    protected float $headerFontSize = 9.0;

    protected float $bodyFontSize = 8.5;

    /**
     * Build a paginated PDF table document and return the raw PDF bytes.
     *
     * @param  string  $title  Title printed at the top of the first page.
     * @param  array<int, string>  $headers  Column header labels.
     * @param  iterable<array<int, mixed>>  $rows  Table rows (values in the same order as $headers).
     * @param  array<int, float>|null  $colWeights  Relative column width weights (defaults to equal width).
     */
    public static function table(string $title, array $headers, iterable $rows, ?array $colWeights = null): string
    {
        $rows = is_array($rows) ? $rows : iterator_to_array($rows);

        return (new self)->build($title, $headers, $rows, $colWeights);
    }

    protected function build(string $title, array $headers, array $rows, ?array $colWeights): string
    {
        $margin = $this->margin;
        $usableWidth = $this->pageWidth - (2 * $margin);
        $columnCount = count($headers);

        if ($colWeights === null || count($colWeights) !== $columnCount) {
            $colWeights = array_fill(0, $columnCount, 1.0);
        }
        $totalWeight = array_sum($colWeights) ?: 1.0;
        $colWidths = array_map(fn ($w) => ($w / $totalWeight) * $usableWidth, $colWeights);

        $top = $this->pageHeight - $margin;

        // Paginate rows so nothing overflows past the bottom margin.
        $pagesOfRows = [];
        $current = [];
        $y = $top - $this->titleHeight - $this->headerRowHeight;
        foreach ($rows as $row) {
            if ($y - $this->rowHeight < $margin) {
                $pagesOfRows[] = $current;
                $current = [];
                $y = $top - $this->headerRowHeight;
            }
            $current[] = $row;
            $y -= $this->rowHeight;
        }
        $pagesOfRows[] = $current;
        if (count($pagesOfRows) > 1 && empty($pagesOfRows[0])) {
            array_shift($pagesOfRows);
        }

        $objects = [];
        $addObject = function (?string $content) use (&$objects) {
            $objects[] = $content;

            return count($objects);
        };

        $catalogNum = $addObject(null);
        $pagesNum = $addObject(null);
        $fontRegularNum = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $fontBoldNum = $addObject('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');

        $pageObjectNums = [];

        $charsPerPointHeader = 1 / ($this->headerFontSize * 0.55);
        $charsPerPointBody = 1 / ($this->bodyFontSize * 0.52);

        foreach ($pagesOfRows as $pageIndex => $pageRows) {
            $parts = ['BT'];
            $cy = $top;

            if ($pageIndex === 0) {
                $parts[] = '/F2 14 Tf';
                $parts[] = '1 0 0 1 '.$this->num($margin).' '.$this->num($cy).' Tm';
                $parts[] = '('.$this->escapeText($title).') Tj';
                $cy -= $this->titleHeight;
            }

            $parts[] = '/F2 '.$this->num($this->headerFontSize).' Tf';
            $cx = $margin;
            foreach ($headers as $i => $header) {
                $maxChars = max(3, (int) ($colWidths[$i] * $charsPerPointHeader));
                $text = $this->truncate((string) $header, $maxChars);
                $parts[] = '1 0 0 1 '.$this->num($cx).' '.$this->num($cy).' Tm';
                $parts[] = '('.$this->escapeText($text).') Tj';
                $cx += $colWidths[$i];
            }
            $cy -= $this->headerRowHeight;

            $parts[] = '/F1 '.$this->num($this->bodyFontSize).' Tf';
            foreach ($pageRows as $row) {
                $cx = $margin;
                foreach (array_values($row) as $i => $value) {
                    if (! isset($colWidths[$i])) {
                        continue;
                    }
                    $maxChars = max(3, (int) ($colWidths[$i] * $charsPerPointBody));
                    $text = $this->truncate((string) $value, $maxChars);
                    $parts[] = '1 0 0 1 '.$this->num($cx).' '.$this->num($cy).' Tm';
                    $parts[] = '('.$this->escapeText($text).') Tj';
                    $cx += $colWidths[$i];
                }
                $cy -= $this->rowHeight;
            }

            $parts[] = 'ET';
            $content = implode("\n", $parts);
            $streamObj = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content);
            $contentNum = $addObject($streamObj);

            $pageObj = sprintf(
                '<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %s %s] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>',
                $pagesNum,
                $this->num($this->pageWidth),
                $this->num($this->pageHeight),
                $fontRegularNum,
                $fontBoldNum,
                $contentNum
            );
            $pageObjectNums[] = $addObject($pageObj);
        }

        $kids = implode(' ', array_map(fn ($n) => "{$n} 0 R", $pageObjectNums));
        $objects[$pagesNum - 1] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', $kids, count($pageObjectNums));
        $objects[$catalogNum - 1] = sprintf('<< /Type /Catalog /Pages %d 0 R >>', $pagesNum);

        // Assemble the file: header, objects (recording byte offsets), xref table, trailer.
        $buffer = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $i => $content) {
            $offsets[] = strlen($buffer);
            $buffer .= sprintf("%d 0 obj\n%s\nendobj\n", $i + 1, $content);
        }
        $xrefOffset = strlen($buffer);
        $buffer .= sprintf("xref\n0 %d\n", count($objects) + 1);
        $buffer .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $buffer .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $buffer .= sprintf("trailer\n<< /Size %d /Root %d 0 R >>\nstartxref\n%d\n%%%%EOF", count($objects) + 1, $catalogNum, $xrefOffset);

        return $buffer;
    }

    /**
     * Format a coordinate/size as a locale-independent decimal string.
     * Uses number_format() (not sprintf's %f) so a server locale with a
     * comma decimal separator can never corrupt the PDF content stream.
     */
    protected function num(float $n): string
    {
        return number_format($n, 2, '.', '');
    }

    /**
     * Convert to Latin-1 (WinAnsi-compatible) and escape PDF string-literal
     * special characters. Characters outside Latin-1 fall back to "?".
     */
    protected function escapeText(string $text): string
    {
        $latin1 = @mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        if ($latin1 === false) {
            $latin1 = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $latin1);
    }

    protected function truncate(string $text, int $maxChars): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        if ($maxChars <= 3) {
            return mb_substr($text, 0, $maxChars);
        }

        return mb_substr($text, 0, $maxChars - 3).'...';
    }
}
