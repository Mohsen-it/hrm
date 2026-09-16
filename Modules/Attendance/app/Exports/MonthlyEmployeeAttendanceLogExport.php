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
        private bool $withLate = true,
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

        if ($this->withLate) {
            $columns['late_minutes'] = ['header' => $this->t('monthly_employee_log.late_minutes'), 'type' => 'string', 'width' => 16];
            $columns['early_leave_minutes'] = ['header' => $this->t('monthly_employee_log.early_leave'), 'type' => 'string', 'width' => 16];
        }

        $currentRow = $this->exporter->writeTitle(
            $sheet,
            $this->t('monthly_employee_log.title'),
            $this->t('monthly_employee_log.export_subtitle', ['employee' => $this->employeeName, 'month' => $this->monthLabel]),
            1,
            count($columns),
        );
        $currentRow++;
        $this->exporter->writeHeaders($sheet, array_column($columns, 'header'), $currentRow);
        $nextRow = $this->exporter->writeRows($sheet, $this->translatedRows(), $columns, $currentRow + 1);

        if ($this->withLate) {
            $totalLate = array_sum(array_map(fn (array $row) => (int) ($row['late_minutes'] ?? 0), $this->rows));
            $totalEarly = array_sum(array_map(fn (array $row) => (int) ($row['early_leave_minutes'] ?? 0), $this->rows));
            $grandTotal = $totalLate + $totalEarly;
            $grandHuman = $this->t('monthly_employee_log.total_late').': '.$this->humanHours($grandTotal);
            // صف الإجمالي: الإجمالي الموحد (دخول + خروج مبكر) في التسمية،
            // وتفصيل كل نوع في عموده.
            $values = array_fill(0, count($columns) - 1, '');
            $values[count($values) - 2] = $this->humanHours($totalLate);
            $values[count($values) - 1] = $this->humanHours($totalEarly);
            $this->exporter->writeSummaryRow(
                $sheet,
                ['label' => $grandHuman, 'values' => $values],
                $nextRow + 1,
                1,
                count($columns),
            );
        }

        $this->exporter->autoSizeColumns($sheet, $columns);

        return $sheet->getParent();
    }

    /**
     * تنسيق الدقائق بصيغة "ساعة (دقيقة)" للإجماليات.
     */
    private function humanHours(int $minutes): string
    {
        return number_format($minutes / 60, 2).' '.$this->t('monthly_employee_log.hours').' ('.$minutes.' '.$this->t('monthly_employee_log.minutes').')';
    }

    /**
     * Translate the schedule status without changing the data used by the UI.
     *
     * @return array<int, array<string, bool|int|string|null>>
     */
    private function translatedRows(): array
    {
        return array_map(function (array $row): array {
            $row['late_minutes'] = (string) ((int) ($row['late_minutes'] ?? 0));
            $row['early_leave_minutes'] = (string) ((int) ($row['early_leave_minutes'] ?? 0));
            // وسم الخروج الليلي (+1): البصمة بتاريخ اليوم التالي لكنها محسوبة لوردية هذا الصف.
            if (! empty($row['is_overnight_checkout']) && is_string($row['last_check_out_at'] ?? null)) {
                $row['last_check_out_at'] .= ' (+1)';
            }
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
