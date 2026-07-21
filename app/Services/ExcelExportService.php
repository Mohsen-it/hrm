<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * ExcelExportService
 *
 * خدمة شاملة لتصدير ملفات Excel بتنسيق احترافي مع دعم كامل للعربية و RTL.
 *
 * الميزات:
 * - دعم RTL كامل
 * - تنسيق احترافي مع ألوان المشروع (Mistral)
 * - دعم الخطوط العربية
 * - تصدير سريع مع headers جاهزة
 * - دعم الأعمدة المخصصة
 * - دعم الصفوف الملونة
 * - دعم الملخصات والتقارير
 */
class ExcelExportService
{
    /**
     * ألوان المشروع (Mistral Design System)
     */
    private const COLOR_PRIMARY = 'FA520F';

    private const COLOR_PRIMARY_LIGHT = 'FFF3ED';

    private const COLOR_SECONDARY = '2C3E50';

    private const COLOR_HEADER_BG = 'FA520F';

    private const COLOR_HEADER_TEXT = 'FFFFFF';

    private const COLOR_ROW_ALT = 'F7F2EC';

    private const COLOR_ROW_WHITE = 'FFFFFF';

    private const COLOR_BORDER = 'DDDDDD';

    private const COLOR_BORDER_LIGHT = 'EEEEEE';

    private const COLOR_SUCCESS = '16A34A';

    private const COLOR_SUCCESS_BG = 'DCFCE7';

    private const COLOR_DANGER = 'DC2626';

    private const COLOR_DANGER_BG = 'FEE2E2';

    private const COLOR_WARNING = 'D97706';

    private const COLOR_WARNING_BG = 'FEF3C7';

    private const COLOR_INFO = '2563EB';

    private const COLOR_INFO_BG = 'DBEAFE';

    /**
     * إنشاء ملف Excel جديد
     */
    public function create(): Spreadsheet
    {
        return new Spreadsheet;
    }

    /**
     * إعداد ورقة العمل الأساسية
     */
    public function setupSheet(
        Worksheet $sheet,
        string $title = 'تقرير',
        bool $rtl = true
    ): void {
        $sheet->setTitle($title);

        if ($rtl) {
            $sheet->setRightToLeft(true);
        }

        $this->applyDefaultFont($sheet);
    }

    /**
     * تطبيق الخط الافتراضي (Cairo)
     */
    private function applyDefaultFont(Worksheet $sheet): void
    {
        $sheet->getParent()->getDefaultStyle()->getFont()
            ->setName('Cairo')
            ->setSize(11);

        $sheet->getStyle('A1:Z1048576')->getFont()
            ->setName('Cairo')
            ->setSize(11);
    }

    /**
     * كتابة عنوان التقرير
     */
    public function writeTitle(
        Worksheet $sheet,
        string $title,
        string $subtitle = '',
        int $startRow = 1,
        int $lastColumn = 7
    ): int {
        $lastColLetter = Coordinate::stringFromColumnIndex($lastColumn);

        // العنوان الرئيسي
        $sheet->setCellValue('A'.$startRow, $title);
        $sheet->mergeCells('A'.$startRow.':'.$lastColLetter.$startRow);
        $sheet->getRowDimension($startRow)->setRowHeight(35);

        $sheet->getStyle('A'.$startRow.':'.$lastColLetter.$startRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => self::COLOR_PRIMARY],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $currentRow = $startRow + 1;

        // العنوان الفرعي
        if (! empty($subtitle)) {
            $sheet->setCellValue('A'.$currentRow, $subtitle);
            $sheet->mergeCells('A'.$currentRow.':'.$lastColLetter.$currentRow);
            $sheet->getRowDimension($currentRow)->setRowHeight(25);

            $sheet->getStyle('A'.$currentRow.':'.$lastColLetter.$currentRow)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => self::COLOR_SECONDARY],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            $currentRow++;
        }

        // التاريخ
        $sheet->setCellValue('A'.$currentRow, 'تاريخ التصدير: '.Carbon::now()->format('Y-m-d H:i'));
        $sheet->mergeCells('A'.$currentRow.':'.$lastColLetter.$currentRow);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);

        $sheet->getStyle('A'.$currentRow.':'.$lastColLetter.$currentRow)->applyFromArray([
            'font' => [
                'size' => 10,
                'color' => ['rgb' => '666666'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        return $currentRow + 1;
    }

    /**
     * كتابة الملخص
     */
    public function writeSummary(
        Worksheet $sheet,
        array $summaryData,
        int $startRow,
        int $lastColumn = 7
    ): int {
        $lastColLetter = Coordinate::stringFromColumnIndex($lastColumn);
        $currentRow = $startRow;

        foreach ($summaryData as $label => $value) {
            $sheet->setCellValue('A'.$currentRow, $label);
            $sheet->setCellValue('B'.$currentRow, $value);

            $sheet->getStyle('A'.$currentRow)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 11,
                ],
            ]);

            $sheet->getStyle('B'.$currentRow)->applyFromArray([
                'font' => [
                    'size' => 11,
                    'color' => ['rgb' => self::COLOR_PRIMARY],
                ],
            ]);

            $currentRow++;
        }

        return $currentRow + 1;
    }

    /**
     * كتابة رؤوس الأعمدة
     */
    public function writeHeaders(
        Worksheet $sheet,
        array $headers,
        int $row,
        int $startColumn = 1
    ): void {
        $col = $startColumn;

        foreach ($headers as $key => $label) {
            $coord = Coordinate::stringFromColumnIndex($col).$row;
            $sheet->setCellValue($coord, $label);
            $col++;
        }

        $lastCol = Coordinate::stringFromColumnIndex($startColumn + count($headers) - 1);
        $headerRange = Coordinate::stringFromColumnIndex($startColumn).$row.':'.$lastCol.$row;

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => self::COLOR_HEADER_TEXT],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => self::COLOR_HEADER_BG],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => self::COLOR_BORDER],
                ],
            ],
        ]);

        $sheet->getRowDimension($row)->setRowHeight(28);
    }

    /**
     * كتابة صفوف البيانات
     */
    public function writeRows(
        Worksheet $sheet,
        iterable $data,
        array $columns,
        int $startRow,
        int $startColumn = 1,
        bool $alternateRows = true
    ): int {
        $row = $startRow;
        $index = 1;

        foreach ($data as $item) {
            $col = $startColumn;

            foreach ($columns as $key => $config) {
                $coord = Coordinate::stringFromColumnIndex($col).$row;
                $value = $this->getNestedValue($item, $config['key'] ?? $key);

                // تحديد نوع البيانات
                $dataType = $config['type'] ?? 'auto';
                $formattedValue = $this->formatValue($value, $dataType, $config);

                if ($dataType === 'string') {
                    $sheet->setCellValueExplicit($coord, (string) $formattedValue, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($coord, $formattedValue);
                }

                // تطبيق التنسيق المخصص للعمود
                if (isset($config['style'])) {
                    $sheet->getStyle($coord)->applyFromArray($config['style']);
                }

                // تطبيق لون الحالة
                if (isset($config['status_color'])) {
                    $statusColor = $this->getStatusColor($value, $config['status_color']);
                    if ($statusColor) {
                        $sheet->getStyle($coord)->applyFromArray([
                            'font' => ['color' => ['rgb' => $statusColor['text']]],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => ['rgb' => $statusColor['bg']],
                            ],
                        ]);
                    }
                }

                $col++;
            }

            // تنسيق الصف بالكامل
            $lastCol = Coordinate::stringFromColumnIndex($startColumn + count($columns) - 1);
            $rowRange = Coordinate::stringFromColumnIndex($startColumn).$row.':'.$lastCol.$row;

            // الصفوف المتبادلة
            if ($alternateRows && ($row - $startRow) % 2 === 1) {
                $sheet->getStyle($rowRange)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => self::COLOR_ROW_ALT],
                    ],
                ]);
            }

            // الحدود
            $sheet->getStyle($rowRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => self::COLOR_BORDER_LIGHT],
                    ],
                ],
            ]);

            // ارتفاع الصف
            $sheet->getRowDimension($row)->setRowHeight(22);

            $index++;
            $row++;
        }

        return $row;
    }

    /**
     * كتابة صف ملخص في النهاية
     */
    public function writeSummaryRow(
        Worksheet $sheet,
        array $summaryData,
        int $row,
        int $startColumn = 1,
        int $lastColumn = 7
    ): void {
        $lastColLetter = Coordinate::stringFromColumnIndex($lastColumn);
        $startColLetter = Coordinate::stringFromColumnIndex($startColumn);

        $sheet->setCellValue($startColLetter.$row, $summaryData['label'] ?? 'الإجمالي');

        $col = $startColumn + 1;
        foreach ($summaryData['values'] ?? [] as $value) {
            $coord = Coordinate::stringFromColumnIndex($col).$row;
            $sheet->setCellValue($coord, $value);
            $col++;
        }

        $rowRange = $startColLetter.$row.':'.$lastColLetter.$row;

        $sheet->getStyle($rowRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => self::COLOR_PRIMARY],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => self::COLOR_PRIMARY_LIGHT],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => self::COLOR_PRIMARY],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension($row)->setRowHeight(28);
    }

    /**
     * تعيين عرض الأعمدة تلقائياً
     */
    public function autoSizeColumns(
        Worksheet $sheet,
        array $columns,
        int $startColumn = 1,
        int $minWidth = 10,
        int $maxWidth = 50
    ): void {
        $col = $startColumn;

        foreach ($columns as $key => $config) {
            $colLetter = Coordinate::stringFromColumnIndex($col);

            if (isset($config['width'])) {
                $sheet->getColumnDimension($colLetter)->setWidth($config['width']);
            } else {
                // حساب العرض بناءً على المحتوى
                $headerWidth = $this->displayWidth($config['header'] ?? $key);
                $contentWidth = $config['max_content_width'] ?? $headerWidth;
                $width = min($maxWidth, max($minWidth, max($headerWidth, $contentWidth) + 4));
                $sheet->getColumnDimension($colLetter)->setWidth($width);
            }

            $col++;
        }
    }

    /**
     * تصدير الملف كـ binary
     */
    public function toBinary(Spreadsheet $spreadsheet): string
    {
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return ob_get_clean();
    }

    /**
     * تصدير الملف كـ response للتحميل
     */
    public function toResponse(Spreadsheet $spreadsheet, string $fileName): array
    {
        return [
            'binary' => $this->toBinary($spreadsheet),
            'fileName' => $fileName.'.xlsx',
            'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * حساب العرض التقريبي للنص
     */
    public function displayWidth(string $value): int
    {
        $width = 0;
        $length = mb_strlen($value, 'UTF-8');

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($value, $i, 1, 'UTF-8');
            $code = mb_ord($char, 'UTF-8');

            // Arabic block
            $isArabic = ($code >= 0x0600 && $code <= 0x08FF)
                || ($code >= 0xFB50 && $code <= 0xFDFF)
                || ($code >= 0xFE70 && $code <= 0xFEFF);

            $width += $isArabic ? 2 : 1;
        }

        return $width;
    }

    /**
     * الحصول على قيمة متداخلة من الكائن
     */
    private function getNestedValue($item, string $key)
    {
        if (is_object($item)) {
            return data_get($item, $key);
        }

        if (is_array($item)) {
            return data_get($item, $key);
        }

        return null;
    }

    /**
     * تنسيق القيمة حسب النوع
     */
    private function formatValue($value, string $type, array $config = [])
    {
        if ($value === null) {
            return $config['default'] ?? '—';
        }

        return match ($type) {
            'string' => (string) $value,
            'integer' => (int) $value,
            'float' => round((float) $value, $config['decimals'] ?? 2),
            'date' => $value instanceof Carbon ? $value->format($config['format'] ?? 'Y-m-d') : $value,
            'datetime' => $value instanceof Carbon ? $value->format($config['format'] ?? 'Y-m-d H:i') : $value,
            'boolean' => $value ? ($config['true'] ?? 'نعم') : ($config['false'] ?? 'لا'),
            'status' => $this->formatStatus($value, $config['map'] ?? []),
            default => $value,
        };
    }

    /**
     * تنسيق حالة
     */
    private function formatStatus($value, array $map): string
    {
        return $map[$value] ?? (string) $value;
    }

    /**
     * الحصول على لون الحالة
     */
    private function getStatusColor($value, array $colorMap): ?array
    {
        return $colorMap[$value] ?? null;
    }

    /**
     * إنشاء تقرير بسيط سريع
     */
    public function quickExport(
        string $title,
        array $headers,
        iterable $data,
        array $columns,
        string $fileName = 'report'
    ): array {
        $spreadsheet = $this->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->setupSheet($sheet, $title);

        $lastColumn = count($headers);
        $currentRow = $this->writeTitle($sheet, $title, '', 1, $lastColumn);
        $currentRow++; // سطر فارغ

        $this->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $currentRow = $this->writeRows($sheet, $data, $columns, $currentRow);

        $this->autoSizeColumns($sheet, $columns);

        return $this->toResponse($spreadsheet, $fileName);
    }

    /**
     * إنشاء تقرير مع ملخص
     */
    public function exportWithSummary(
        string $title,
        string $subtitle,
        array $summaryData,
        array $headers,
        iterable $data,
        array $columns,
        string $fileName = 'report'
    ): array {
        $spreadsheet = $this->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->setupSheet($sheet, $title);

        $lastColumn = count($headers);
        $currentRow = $this->writeTitle($sheet, $title, $subtitle, 1, $lastColumn);
        $currentRow++;

        $currentRow = $this->writeSummary($sheet, $summaryData, $currentRow, $lastColumn);
        $currentRow++;

        $this->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $currentRow = $this->writeRows($sheet, $data, $columns, $currentRow);

        $this->autoSizeColumns($sheet, $columns);

        return $this->toResponse($spreadsheet, $fileName);
    }

    /**
     * إنشاء تقرير مع ملخص في النهاية
     */
    public function exportWithFooterSummary(
        string $title,
        string $subtitle,
        array $headers,
        iterable $data,
        array $columns,
        array $footerSummary,
        string $fileName = 'report'
    ): array {
        $spreadsheet = $this->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->setupSheet($sheet, $title);

        $lastColumn = count($headers);
        $currentRow = $this->writeTitle($sheet, $title, $subtitle, 1, $lastColumn);
        $currentRow++;

        $this->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $currentRow = $this->writeRows($sheet, $data, $columns, $currentRow);
        $currentRow++;

        $this->writeSummaryRow($sheet, $footerSummary, $currentRow, 1, $lastColumn);

        $this->autoSizeColumns($sheet, $columns);

        return $this->toResponse($spreadsheet, $fileName);
    }
}
