<?php

namespace Modules\Attendance\Exports;

use App\Services\ExcelExportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Excel rendering for a monthly employee attendance log.
 */
class MonthlyEmployeeAttendanceLogExport
{
    /**
     * @param  array<int, array<string, bool|int|string|null>>  $rows
     */
    public function __construct(
        private ExcelExportService $exporter,
        private string $employeeName,
        private string $monthLabel,
        private array $rows,
    ) {}

    /**
     * Build the workbook.
     */
    public function build(): Spreadsheet
    {
        $sheet = $this->exporter->create()->getActiveSheet();
        // Excel sheet names are limited to 31 characters.
        $this->exporter->setupSheet($sheet, mb_substr($this->t('monthly_employee_log.title'), 0, 31));

        $columns = [
            'date' => ['header' => $this->t('fields.date'), 'type' => 'string', 'width' => 14],
            'day_name' => ['header' => $this->t('monthly_employee_log.day'), 'type' => 'string', 'width' => 16],
            'schedule_status' => ['header' => $this->t('monthly_employee_log.schedule_status'), 'type' => 'string', 'width' => 16],
            'expected_check_in' => ['header' => $this->t('fields.expected_check_in'), 'type' => 'string', 'width' => 16],
            'expected_check_out' => ['header' => $this->t('fields.expected_check_out'), 'type' => 'string', 'width' => 16],
            'check_in_window' => ['header' => $this->t('monthly_employee_log.check_in_window'), 'type' => 'string', 'width' => 18],
            'first_check_in_at' => ['header' => $this->t('fields.first_check_in_at'), 'type' => 'string', 'width' => 20],
            'check_out_window' => ['header' => $this->t('monthly_employee_log.check_out_window'), 'type' => 'string', 'width' => 18],
            'last_check_out_at' => ['header' => $this->t('fields.last_check_out_at'), 'type' => 'string', 'width' => 20],
        ];
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            $this->t('monthly_employee_log.title'),
            $this->t('monthly_employee_log.export_subtitle', ['employee' => $this->employeeName, 'month' => $this->monthLabel]),
            1,
            count($columns),
        );
        $currentRow++;
        $this->exporter->writeHeaders($sheet, array_column($columns, 'header'), $currentRow);
        $this->exporter->writeRows($sheet, $this->translatedRows(), $columns, $currentRow + 1);
        $this->exporter->autoSizeColumns($sheet, $columns);

        return $sheet->getParent();
    }

    /**
     * Translate the schedule status without changing the data used by the UI.
     *
     * @return array<int, array<string, bool|int|string|null>>
     */
    private function translatedRows(): array
    {
        return array_map(function (array $row): array {
            $row['schedule_status'] = match ($row['schedule_status'] ?? null) {
                'work' => 'دوام',
                'rest' => 'يوم راحة',
                'leave_excused' => 'إجازة',
                'swap' => 'تبديل دوام',
                'unassigned' => 'بدون إسناد',
                default => (string) ($row['schedule_status'] ?? '—'),
            };

            return $row;
        }, $this->rows);
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
