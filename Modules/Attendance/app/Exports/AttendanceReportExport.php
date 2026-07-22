<?php

namespace Modules\Attendance\Exports;

use App\Services\ExcelExportService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * AttendanceReportExport
 *
 * تصدير التقارير الإجمالية (KPIs + ترندات + مقارنة الأقسام + الأكثر تأخيراً).
 */
class AttendanceReportExport
{
    private ExcelExportService $exporter;

    public function __construct(
        private string $fromDate,
        private string $toDate,
        private string $date = '',
        private array $kpis = [],
        private array $trend = [],
        private array $departmentComparison = [],
        private array $topLate = [],
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'تقرير الحضور');

        $lastColumn = 5;
        $subtitle = 'الفترة: '.($this->fromDate ?: '...').' إلى '.($this->toDate ?: '...');
        if ($this->date) {
            $subtitle .= '  |  التاريخ: '.$this->date;
        }
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'تقرير الحضور الشامل',
            $subtitle,
            1,
            $lastColumn
        );

        $currentRow++;
        $currentRow = $this->writeKpisSection($sheet, $currentRow, $lastColumn);
        $currentRow += 2;
        $currentRow = $this->writeTrendSection($sheet, $currentRow, $lastColumn);
        $currentRow += 2;
        $currentRow = $this->writeDepartmentSection($sheet, $currentRow, $lastColumn);
        $currentRow += 2;
        $currentRow = $this->writeTopLateSection($sheet, $currentRow, $lastColumn);

        return $spreadsheet;
    }

    private function writeKpisSection($sheet, int $startRow, int $lastColumn): int
    {
        $headers = ['المؤشر', 'القيمة'];
        $headers[0] = 'المؤشر';
        $headers[1] = 'القيمة';

        $sheet->setCellValue('A'.$startRow, 'المؤشرات الرئيسية (KPIs)');
        $sheet->mergeCells('A'.$startRow.':B'.$startRow);
        $sheet->getStyle('A'.$startRow.':B'.$startRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '2C3E50']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($startRow)->setRowHeight(26);

        $row = $startRow + 1;
        $kpiKeys = [
            'total_employees' => 'إجمالي الموظفين',
            'present' => 'الحضور',
            'absent' => 'الغياب',
            'late' => 'المتأخرون',
            'on_leave' => 'في إجازة',
            'attendance_rate' => 'نسبة الحضور %',
        ];
        foreach ($kpiKeys as $key => $label) {
            $value = $this->kpis[$key] ?? null;
            $display = is_numeric($value) && $key === 'attendance_rate'
                ? round((float) $value, 2).'%'
                : ($value ?? '—');
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $display);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
        }

        $sheet->getStyle('A'.$startRow.':B'.($row - 1))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'DDDDDD'],
                ],
            ],
        ]);

        return $row;
    }

    private function writeTrendSection($sheet, int $startRow, int $lastColumn): int
    {
        $sheet->setCellValue('A'.$startRow, 'الاتجاه اليومي');
        $sheet->mergeCells('A'.$startRow.':'.$this->col($lastColumn).$startRow);
        $sheet->getStyle('A'.$startRow.':'.$this->col($lastColumn).$startRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '2C3E50']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($startRow)->setRowHeight(26);

        $row = $startRow + 1;
        $headers = ['التاريخ', 'الحضور', 'الغياب', 'المتأخرون', 'نسبة الحضور %'];
        $col = 1;
        foreach ($headers as $h) {
            $coord = $this->col($col).$row;
            $sheet->setCellValue($coord, $h);
            $col++;
        }
        $sheet->getStyle('A'.$row.':'.$this->col($lastColumn).$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FA520F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $row++;

        foreach ($this->trend as $row_data) {
            $sheet->setCellValue('A'.$row, $row_data['date'] ?? '');
            $sheet->setCellValue('B'.$row, $row_data['present'] ?? 0);
            $sheet->setCellValue('C'.$row, $row_data['absent'] ?? 0);
            $sheet->setCellValue('D'.$row, $row_data['late'] ?? 0);
            $rate = $row_data['attendance_rate'] ?? 0;
            $sheet->setCellValue('E'.$row, is_numeric($rate) ? round((float) $rate, 2).'%' : ($rate ?? '—'));
            $row++;
        }

        if ($row - 1 > $startRow) {
            $sheet->getStyle('A'.($startRow + 1).':'.$this->col($lastColumn).($row - 1))->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'EEEEEE'],
                    ],
                ],
            ]);
        }

        return $row;
    }

    private function writeDepartmentSection($sheet, int $startRow, int $lastColumn): int
    {
        $sheet->setCellValue('A'.$startRow, 'مقارنة الأقسام');
        $sheet->mergeCells('A'.$startRow.':'.$this->col($lastColumn).$startRow);
        $sheet->getStyle('A'.$startRow.':'.$this->col($lastColumn).$startRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '2C3E50']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($startRow)->setRowHeight(26);

        $row = $startRow + 1;
        $headers = ['القسم', 'إجمالي الموظفين', 'الحضور', 'الغياب', 'نسبة الحضور %'];
        $col = 1;
        foreach ($headers as $h) {
            $coord = $this->col($col).$row;
            $sheet->setCellValue($coord, $h);
            $col++;
        }
        $sheet->getStyle('A'.$row.':'.$this->col($lastColumn).$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FA520F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $row++;

        foreach ($this->departmentComparison as $row_data) {
            $sheet->setCellValue('A'.$row, $row_data['department_name'] ?? '—');
            $sheet->setCellValue('B'.$row, $row_data['total_employees'] ?? 0);
            $sheet->setCellValue('C'.$row, $row_data['present'] ?? 0);
            $sheet->setCellValue('D'.$row, $row_data['absent'] ?? 0);
            $rate = $row_data['attendance_rate'] ?? 0;
            $sheet->setCellValue('E'.$row, is_numeric($rate) ? round((float) $rate, 2).'%' : ($rate ?? '—'));
            $row++;
        }

        if ($row - 1 > $startRow) {
            $sheet->getStyle('A'.($startRow + 1).':'.$this->col($lastColumn).($row - 1))->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'EEEEEE'],
                    ],
                ],
            ]);
        }

        return $row;
    }

    private function writeTopLateSection($sheet, int $startRow, int $lastColumn): int
    {
        $sheet->setCellValue('A'.$startRow, 'الأكثر تأخراً');
        $sheet->mergeCells('A'.$startRow.':'.$this->col($lastColumn).$startRow);
        $sheet->getStyle('A'.$startRow.':'.$this->col($lastColumn).$startRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '2C3E50']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($startRow)->setRowHeight(26);

        $row = $startRow + 1;
        $headers = ['#', 'الموظف', 'رمز الموظف', 'القسم', 'دقائق التأخير'];
        $col = 1;
        foreach ($headers as $h) {
            $coord = $this->col($col).$row;
            $sheet->setCellValue($coord, $h);
            $col++;
        }
        $sheet->getStyle('A'.$row.':'.$this->col($lastColumn).$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FA520F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $row++;

        $i = 1;
        foreach ($this->topLate as $row_data) {
            $sheet->setCellValue('A'.$row, $i);
            $sheet->setCellValue('B'.$row, $row_data['user_name'] ?? $row_data['name'] ?? '—');
            $sheet->setCellValue('C'.$row, $row_data['employee_code'] ?? '—');
            $sheet->setCellValue('D'.$row, $row_data['department_name'] ?? '—');
            $sheet->setCellValue('E'.$row, $row_data['late_minutes'] ?? $row_data['total_late'] ?? 0);
            $i++;
            $row++;
        }

        if ($row - 1 > $startRow) {
            $sheet->getStyle('A'.($startRow + 1).':'.$this->col($lastColumn).($row - 1))->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'EEEEEE'],
                    ],
                ],
            ]);
        }

        return $row;
    }

    private function col(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    public function toBinary(): string
    {
        return $this->exporter->toBinary($this->build());
    }
}
