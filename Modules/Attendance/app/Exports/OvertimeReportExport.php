<?php

namespace Modules\Attendance\Exports;

use App\Services\ExcelExportService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Excel rendering for a user overtime report.
 */
class OvertimeReportExport
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private ExcelExportService $exporter,
        private string $employeeName,
        private string $dateRange,
        private array $rows,
    ) {}

    /**
     * Build the workbook.
     */
    public function build(): Spreadsheet
    {
        $sheet = $this->exporter->create()->getActiveSheet();
        // Excel sheet names are limited to 31 characters.
        $this->exporter->setupSheet($sheet, mb_substr($this->t('overtime_report.title'), 0, 31));

        $columns = [
            'date' => ['header' => $this->t('fields.date'), 'type' => 'string', 'width' => 14],
            'day_name' => ['header' => $this->t('monthly_employee_log.day'), 'type' => 'string', 'width' => 16],
            'expected_check_in' => ['header' => $this->t('fields.expected_check_in'), 'type' => 'string', 'width' => 16],
            'expected_check_out' => ['header' => $this->t('fields.expected_check_out'), 'type' => 'string', 'width' => 16],
            'actual_check_in' => ['header' => $this->t('fields.first_check_in_at'), 'type' => 'string', 'width' => 16],
            'actual_check_out' => ['header' => $this->t('fields.last_check_out_at'), 'type' => 'string', 'width' => 16],
            'expected_work_minutes' => ['header' => $this->t('overtime_report.expected_work'), 'type' => 'number', 'width' => 16],
            'work_minutes' => ['header' => $this->t('fields.work_human'), 'type' => 'number', 'width' => 14],
            'overtime_minutes' => ['header' => $this->t('fields.overtime_minutes'), 'type' => 'number', 'width' => 18],
        ];
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            $this->t('overtime_report.title'),
            $this->t('overtime_report.subtitle', ['employee' => $this->employeeName, 'from' => $this->dateRange]),
            1,
            count($columns),
        );
        $currentRow++;
        $this->exporter->writeHeaders($sheet, array_column($columns, 'header'), $currentRow);

        // Filter only days with overtime > 0 وحوّل كل القيم للساعات
        $filtered = array_values(array_filter($this->rows, fn ($row) => ($row['overtime_minutes'] ?? 0) > 0));

        // Totals must be computed on raw minutes BEFORE formatting to avoid double-division
        $totalOvertime = array_sum(array_column($filtered, 'overtime_minutes'));
        $totalExpectedWork = array_sum(array_column($filtered, 'expected_work_minutes'));
        $totalActualWork = array_sum(array_column($filtered, 'work_minutes'));

        $overtimeRows = array_map(fn ($r) => array_merge($r, [
            'expected_work_minutes' => $this->formatHours((int) ($r['expected_work_minutes'] ?? 0)),
            'work_minutes' => $this->formatHours((int) ($r['work_minutes'] ?? 0)),
            'overtime_minutes' => $this->formatHours((int) ($r['overtime_minutes'] ?? 0)),
        ]), $filtered);

        $this->exporter->writeRows($sheet, $overtimeRows, $columns, $currentRow + 1);

        // Add total overtime row — accurate aggregation with H:MM display
        $totalRow = $currentRow + 1 + count($overtimeRows) + 1;
        // When there are no overtime rows, totalRow is header+2; adjust to avoid overwriting header
        if (count($overtimeRows) === 0) {
            $totalRow = $currentRow + 2;
        }

        // Write total label بالساعات فقط (كما طلب المستخدم) - PhpSpreadsheet 5.x removed setCellValueByColumnAndRow/getStyleByColumnAndRow
        $sheet->setCellValue(Coordinate::stringFromColumnIndex(1).$totalRow, $this->t('overtime_report.total_overtime'));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex(7).$totalRow, $this->formatHours((int) $totalExpectedWork));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex(8).$totalRow, $this->formatHours((int) $totalActualWork));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex(9).$totalRow, $this->formatHours((int) $totalOvertime));

        $totalRange = Coordinate::stringFromColumnIndex(1).$totalRow.':'.Coordinate::stringFromColumnIndex(count($columns)).$totalRow;
        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FFFF5722']],
        ]);

        $this->exporter->autoSizeColumns($sheet, $columns);

        return $sheet->getParent();
    }

    private function formatHours(int $minutes): string
    {
        return number_format(max(0, $minutes) / 60, 2, '.', '');
    }

    private function formatHuman(int $minutes): string
    {
        return $this->formatHours($minutes);
    }

    /**
     * Resolve a translation from the Attendance module namespace.
     *
     * @param  array<string, string>  $replace
     */
    private function t(string $key, array $replace = []): string
    {
        return (string) __('attendance::attendance.'.$key, $replace);
    }
}
