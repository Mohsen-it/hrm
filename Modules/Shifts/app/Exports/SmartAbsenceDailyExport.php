<?php

namespace Modules\Shifts\Exports;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * SmartAbsenceDailyExport
 *
 * Renders the daily smart-absence report as a fully formatted .xlsx file
 * with full Arabic / RTL support:
 *  - Sheet is set to right-to-left so columns read A..F from the right.
 *  - All cell text is rendered in a Unicode-compliant font (Cairo) so
 *    Arabic glyphs, diacritics, and symbols render correctly in Excel.
 *  - Numeric values (employee code) are written as text to avoid
 *    Excel mangling leading zeros.
 *  - Headers, title, and summary are coloured with the project's
 *    mistral-primary brand colour (#FA520F).
 */
class SmartAbsenceDailyExport
{
    /** Columns emitted in the sheet (1-based order matches A..F). */
    private const COLUMNS = [
        'index' => '#',
        'name' => 'اسم الموظف',
        'employee_code' => 'رمز الموظف',
        'department' => 'القسم',
        'rotation' => 'الدورية',
        'rotation_group' => 'مجموعة الدورية',
        'status' => 'الحالة',
    ];

    private const HEADER_ROW = 4;

    private const DATA_START_ROW = 5;

    public function __construct(
        private Carbon $date,
        private int $totalExpected,
        private int $totalAbsent,
        private iterable $absentDetails,
        private string $statusLabel = 'غياب',
    ) {}

    /**
     * Build the .xlsx file in memory and return the raw binary content.
     */
    public function toBinary(): string
    {
        $spreadsheet = $this->build();
        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');

        return ob_get_clean();
    }

    /**
     * Build the configured Spreadsheet (exposed for tests).
     */
    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('تقرير الغياب');

        $this->applyRtl($sheet);
        $this->applyFont($sheet);
        $this->writeTitle($sheet);
        $this->writeSummary($sheet);
        $this->writeHeaders($sheet);
        $this->writeRows($sheet);
        $this->autosizeColumns($sheet);

        return $spreadsheet;
    }

    private function applyRtl(Worksheet $sheet): void
    {
        $sheet->setRightToLeft(true);

        foreach (range('A', 'G') as $column) {
            $sheet->getStyle($column)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }
    }

    private function applyFont(Worksheet $sheet): void
    {
        // Cairo ships with Windows and supports full Arabic + diacritics;
        // we set it on the default style so every cell picks it up.
        $sheet->getParent()->getDefaultStyle()->getFont()
            ->setName('Cairo')
            ->setSize(11);

        $sheet->getStyle('A1:Z1048576')->getFont()
            ->setName('Cairo')
            ->setSize(11);
    }

    private function writeTitle(Worksheet $sheet): void
    {
        $sheet->setCellValue('A1', 'تقرير الغياب الذكي');
        $sheet->mergeCells('A1:G1');
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->setCellValue('A2', 'التاريخ: ' . $this->date->format('Y-m-d'));
        $sheet->mergeCells('A2:G2');
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->getStyle('A1:G2')->getFont()
            ->setBold(true)
            ->setSize(13);

        $sheet->getStyle('A1:G1')
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A2:G2')
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function writeSummary(Worksheet $sheet): void
    {
        $sheet->setCellValue('A3', 'المتوقع: ' . $this->totalExpected);
        $sheet->setCellValue('C3', 'الغائبون: ' . $this->totalAbsent);
        $sheet->mergeCells('A3:B3');
        $sheet->mergeCells('C3:D3');
        $sheet->getRowDimension(3)->setRowHeight(20);

        $sheet->getStyle('A3:D3')->getFont()->setBold(true)->setSize(12);
    }

    private function writeHeaders(Worksheet $sheet): void
    {
        $columnIndex = 1;
        foreach (self::COLUMNS as $label) {
            $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex) . self::HEADER_ROW;
            $sheet->setCellValue($coordinate, $label);
            $columnIndex++;
        }

        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::COLUMNS));
        $headerRange = 'A' . self::HEADER_ROW . ':' . $lastColumn . self::HEADER_ROW;

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FA520F'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(24);
    }

    private function writeRows(Worksheet $sheet): void
    {
        $row = self::DATA_START_ROW;
        $index = 1;

        foreach ($this->absentDetails as $employee) {
            $sheet->setCellValue('A' . $row, $index);
            $sheet->setCellValue('B' . $row, (string) ($employee->name ?? ''));
            // Force employee code to render as text so leading zeros survive.
            $sheet->setCellValueExplicit(
                'C' . $row,
                (string) ($employee->employee_code ?? ''),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $sheet->setCellValue('D' . $row, (string) ($employee->department_name ?? '—'));
            $sheet->setCellValue('E' . $row, (string) ($employee->rotation_name ?? '—'));
            $sheet->setCellValue('F' . $row, (string) ($employee->rotation_group_name ?? '—'));
            $sheet->setCellValue('G' . $row, $this->statusLabel);

            $sheet->getRowDimension($row)->setRowHeight(22);
            $index++;
            $row++;
        }

        $lastRow = max(self::DATA_START_ROW, $row - 1);
        $bodyRange = 'A' . self::DATA_START_ROW . ':G' . $lastRow;

        $sheet->getStyle($bodyRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'DDDDDD'],
                ],
            ],
        ]);

        // Alternate row banding for readability.
        for ($r = self::DATA_START_ROW; $r <= $lastRow; $r++) {
            if (($r - self::DATA_START_ROW) % 2 === 1) {
                $sheet->getStyle('A' . $r . ':G' . $r)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F7F2EC'],
                    ],
                ]);
            }
        }

        // Center-align the index & status columns; right-align the rest (already set globally).
        $sheet->getStyle('A' . self::DATA_START_ROW . ':A' . $lastRow)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G' . self::DATA_START_ROW . ':G' . $lastRow)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function autosizeColumns(Worksheet $sheet): void
    {
        // Approximate width using UTF-8 character counts because Excel
        // auto-size is unreliable for Arabic strings.
        $maxWidths = [];
        foreach (self::COLUMNS as $key => $label) {
            $maxWidths[$key] = $this->displayWidth($label);
        }

        foreach ($this->absentDetails as $employee) {
            foreach ([
                'name' => (string) ($employee->name ?? ''),
                'employee_code' => (string) ($employee->employee_code ?? ''),
                'department' => (string) ($employee->department_name ?? ''),
                'rotation' => (string) ($employee->rotation_name ?? ''),
                'rotation_group' => (string) ($employee->rotation_group_name ?? ''),
            ] as $key => $value) {
                $width = $this->displayWidth($value);
                if ($width > $maxWidths[$key]) {
                    $maxWidths[$key] = $width;
                }
            }
        }

        $columnIndex = 1;
        foreach (self::COLUMNS as $key => $label) {
            $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
            // Index column stays narrow, all others get a comfortable pad.
            $width = $key === 'index' ? 6 : min(60, $maxWidths[$key] + 4);
            $sheet->getColumnDimension($coordinate)->setWidth($width);
            $columnIndex++;
        }
    }

    /**
     * Approximate display width for mixed Arabic/Latin strings.
     * Counts each Arabic char as 2 visual units and each Latin/digit as 1.
     */
    private function displayWidth(string $value): int
    {
        $width = 0;
        $length = mb_strlen($value, 'UTF-8');
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($value, $i, 1, 'UTF-8');
            $code = mb_ord($char, 'UTF-8');
            // Arabic block: U+0600 - U+06FF
            // Arabic Supplement: U+0750 - U+077F
            // Arabic Extended-A: U+08A0 - U+08FF
            // Arabic Presentation Forms-A/B: U+FB50 - U+FDFF, U+FE70 - U+FEFF
            $isArabic = ($code >= 0x0600 && $code <= 0x08FF)
                || ($code >= 0xFB50 && $code <= 0xFDFF)
                || ($code >= 0xFE70 && $code <= 0xFEFF);
            $width += $isArabic ? 2 : 1;
        }

        return $width;
    }
}
