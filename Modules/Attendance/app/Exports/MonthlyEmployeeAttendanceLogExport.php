<?php

namespace Modules\Attendance\Exports;

use App\Services\ExcelExportService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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

        // Days after today are in the future — they carry no punches and must
        // never appear in a printed/exported monthly record.
        $this->rows = array_values(array_filter(
            $this->rows,
            fn (array $row) => empty($row['is_future']),
        ));

        $columns = [
            'date' => ['header' => $this->t('fields.date'), 'type' => 'string', 'width' => 14],
            'day_name' => ['header' => $this->t('monthly_employee_log.day'), 'type' => 'string', 'width' => 16],
            'schedule_status' => ['header' => $this->t('monthly_employee_log.schedule_status'), 'type' => 'rich', 'width' => 22],
            'expected_check_in' => ['header' => $this->t('fields.expected_check_in'), 'type' => 'string', 'width' => 16],
            'expected_check_out' => ['header' => $this->t('fields.expected_check_out'), 'type' => 'string', 'width' => 16],
            'first_check_in_at' => ['header' => $this->t('fields.first_check_in_at'), 'type' => 'string', 'width' => 20],
            'last_check_out_at' => ['header' => $this->t('fields.last_check_out_at'), 'type' => 'string', 'width' => 20],
        ];

        if ($this->withLate) {
            $columns['late_minutes'] = ['header' => $this->t('monthly_employee_log.late_minutes'), 'type' => 'string', 'width' => 16];
            $columns['early_leave_minutes'] = ['header' => $this->t('monthly_employee_log.early_leave'), 'type' => 'string', 'width' => 16];
        }

        // Notes always last — same column order as the on-screen table.
        $columns['notes'] = ['header' => $this->t('monthly_employee_log.notes'), 'type' => 'rich', 'width' => 40];

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
        $this->enlargeFlaggedRows($sheet, $currentRow + 1, count($columns));

        if ($this->withLate) {
            $totalLate = array_sum(array_map(fn (array $row) => (int) ($row['late_minutes'] ?? 0), $this->rows));
            $totalEarly = array_sum(array_map(fn (array $row) => (int) ($row['early_leave_minutes'] ?? 0), $this->rows));
            $grandTotal = $totalLate + $totalEarly;
            $grandHuman = $this->t('monthly_employee_log.total_late').': '.$this->humanHours($grandTotal);
            // صف الإجمالي: الإجمالي الموحد (دخول + خروج مبكر) في التسمية،
            // وتفصيل كل نوع في عموده الخاص (موضع العمود يُحل بالاسم حتى لو
            // تغيّر ترتيب الأعمدة — عمود الملاحظات هو الأخير الآن).
            $values = array_fill(0, count($columns) - 1, '');
            $keys = array_keys($columns);
            $lateIndex = array_search('late_minutes', $keys, true);
            $earlyIndex = array_search('early_leave_minutes', $keys, true);
            if ($lateIndex !== false && $lateIndex > 0) {
                $values[$lateIndex - 1] = $this->humanHours($totalLate);
            }
            if ($earlyIndex !== false && $earlyIndex > 0) {
                $values[$earlyIndex - 1] = $this->humanHours($totalEarly);
            }
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
     * Vacation days keep their type ("إجازة: سنوية") in the type's own color,
     * and justified days carry the justification + reason in the notes column —
     * both rendered as RichText so the colors survive in Excel.
     *
     * @return array<int, array<string, mixed>>
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
            $statusLabel = match ($row['schedule_status'] ?? null) {
                'work' => 'دوام',
                'rest' => 'يوم راحة',
                'leave_excused' => 'إجازة',
                'swap' => 'تبديل دوام',
                'unassigned' => 'بدون إسناد',
                default => (string) ($row['schedule_status'] ?? '—'),
            };

            // A vacation only means something on a day the employee was expected
            // to work (leave_excused). On rotation rest days the resolver keeps
            // "rest" and the vacation must not repaint the row.
            $vacationType = ($row['schedule_status'] ?? null) === 'leave_excused'
                && is_string($row['vacation_type'] ?? null) && $row['vacation_type'] !== ''
                ? $row['vacation_type']
                : null;

            if ($vacationType !== null) {
                $statusLabel = $this->t('monthly_employee_log.vacation_prefix').': '.$vacationType;
                $statusRich = new RichText;
                $statusRich->createTextRun($statusLabel)
                    ->getFont()->setBold(true)->setSize(14)->setColor(new Color($this->sanitizeColor($row['vacation_type_color'] ?? null)));
                $row['schedule_status'] = $statusRich;
            } else {
                $row['schedule_status'] = $statusLabel;
            }

            $row['notes'] = $this->formatNotesCell($row);

            return $row;
        }, $this->rows);
    }

    /**
     * Build the notes cell: one line per fact, justification first.
     *
     * @param  array<string, mixed>  $row
     */
    private function formatNotesCell(array $row): RichText|string
    {
        $lines = [];

        if (! empty($row['has_justification'])) {
            $reason = is_string($row['justification_reason'] ?? null) && $row['justification_reason'] !== ''
                ? $row['justification_reason']
                : '—';
            $lines[] = [
                'text' => $this->t('monthly_employee_log.justification_note').': '.$this->t('monthly_employee_log.reason').': '.$reason,
                'color' => 'B45309',
                'bold' => true,
            ];
        }

        if ($lines === []) {
            return '—';
        }

        $rich = new RichText;
        foreach ($lines as $i => $line) {
            if ($i > 0) {
                $rich->createText("\n");
            }
            $run = $rich->createTextRun($line['text']);
            $run->getFont()->setColor(new Color($line['color']))->setSize(13);
            if ($line['bold']) {
                $run->getFont()->setBold(true);
            }
        }

        return $rich;
    }

    /**
     * Give flagged rows room for their enlarged text: taller row + wrapped
     * notes cell so the bigger justification/vacation runs never overflow.
     */
    private function enlargeFlaggedRows(Worksheet $sheet, int $firstRow, int $columnCount): void
    {
        $notesCol = Coordinate::stringFromColumnIndex($columnCount);

        foreach (array_values($this->rows) as $index => $row) {
            $isVacation = ($row['schedule_status'] ?? null) === 'leave_excused'
                && ! empty($row['vacation_type']);

            if (empty($row['has_justification']) && ! $isVacation) {
                continue;
            }

            $excelRow = $firstRow + $index;
            $sheet->getRowDimension($excelRow)->setRowHeight(34);
            $sheet->getStyle($notesCol.$excelRow)->getAlignment()->setWrapText(true);
        }
    }

    /**
     * Normalize a vacation-type color to a 6-digit RGB hex string.
     *
     * Falls back to --color-mistral-status-vacation (#0891b2), the same token
     * the UI uses (CHART_VACATION in resources/js/utils/chartPalette.js).
     */
    private function sanitizeColor(mixed $color): string
    {
        $hex = strtoupper(ltrim((string) ($color ?? ''), '#'));

        if (preg_match('/^[0-9A-F]{6}$/', $hex) === 1) {
            return $hex;
        }

        if (preg_match('/^[0-9A-F]{3}$/', $hex) === 1) {
            return $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '0891B2';
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
