<?php

namespace Modules\Shifts\Exports;

use App\Services\ExcelExportService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * SmartAbsenceMonthlyExport
 *
 * Renders the monthly / date-range smart-absence report as a fully formatted
 * .xlsx file with full Arabic / RTL support using ExcelExportService.
 */
class SmartAbsenceMonthlyExport
{
    private ExcelExportService $exporter;

    public function __construct(
        private string $fromDate,
        private string $toDate,
        private int $totalExpectedDays,
        private int $totalAbsentDays,
        private iterable $employees,
        private string $statusLabel = 'غياب',
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    /**
     * Build the .xlsx file in memory and return the raw binary content.
     */
    public function toBinary(): string
    {
        return $this->exporter->toBinary($this->build());
    }

    /**
     * Build the configured Spreadsheet (exposed for tests).
     */
    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'تقرير الغياب الشهري');

        $headers = [
            '#', 'اسم الموظف', 'رمز الموظف', 'المسمى الوظيفي', 'القسم', 'الفرع',
            'المنصب', 'الدرجة الوظيفية', 'رقم الهاتف', 'الدورية', 'مجموعة الدورية',
            'أيام الدوام المتوقعة', 'أيام الحضور', 'أيام الغياب', 'أيام الغياب (التواريخ)', 'تفاصيل الأيام', 'نسبة الحضور %',
        ];
        $lastColumn = count($headers);

        // كتابة العنوان
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'تقرير الغياب الذكي',
            "الفترة: {$this->fromDate} ← {$this->toDate}",
            1,
            $lastColumn
        );

        // كتابة الملخص
        $summaryData = [
            'الأيام المتوقعة' => $this->totalExpectedDays,
            'أيام الغياب' => $this->totalAbsentDays,
            'نسبة الحضور' => $this->totalExpectedDays > 0
                ? (int) round((($this->totalExpectedDays - $this->totalAbsentDays) / $this->totalExpectedDays) * 100).'%'
                : '100%',
        ];
        $currentRow = $this->exporter->writeSummary($sheet, $summaryData, $currentRow, $lastColumn);

        // دليل ألوان تفاصيل الأيام
        $currentRow = $this->writeDayDetailsLegend($sheet, $currentRow, $lastColumn);

        // كتابة رؤوس الأعمدة
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        // كتابة البيانات
        $columns = [
            'index' => ['key' => 'index', 'type' => 'integer', 'width' => 6],
            'name' => ['key' => 'name', 'type' => 'string', 'width' => 25],
            'employee_code' => ['key' => 'employee_code', 'type' => 'string', 'width' => 14],
            'job_title' => ['key' => 'job_title', 'type' => 'string', 'width' => 18],
            'department' => ['key' => 'department_name', 'type' => 'string', 'width' => 20],
            'branch' => ['key' => 'branch_name', 'type' => 'string', 'width' => 18],
            'position' => ['key' => 'position_name', 'type' => 'string', 'width' => 18],
            'grade' => ['key' => 'grade_name', 'type' => 'string', 'width' => 12],
            'phone' => ['key' => 'phone', 'type' => 'string', 'width' => 14],
            'rotation' => ['key' => 'rotation_name', 'type' => 'string', 'width' => 15],
            'rotation_group' => ['key' => 'rotation_group_name', 'type' => 'string', 'width' => 18],
            'expected_days' => ['key' => 'expected_days', 'type' => 'integer', 'width' => 16],
            'present_days' => ['key' => 'present_days', 'type' => 'integer', 'width' => 12],
            'absent_days' => ['key' => 'absent_days', 'type' => 'integer', 'width' => 12],
            'absent_dates' => ['key' => 'absent_dates', 'type' => 'string', 'width' => 32],
            // 'rich' keeps the per-run font colors of the RichText value.
            'day_details' => ['key' => 'day_details_text', 'type' => 'rich', 'width' => 55],
            'attendance_rate' => ['key' => 'attendance_rate', 'type' => 'integer', 'width' => 12],
        ];

        // تحضير البيانات مع الفهرس
        $index = 1;
        $preparedData = [];
        foreach ($this->employees as $employee) {
            $employee->index = $index;
            $employee->status = $this->statusLabel;
            if (is_array($employee->absent_dates)) {
                $employee->absent_dates = implode('، ', $employee->absent_dates);
            }
            $employee->day_details_text = $this->formatDayDetails($employee->day_details ?? []);
            $preparedData[] = $employee;
            $index++;
        }

        $this->exporter->writeRows($sheet, $preparedData, $columns, $currentRow);
        $this->exporter->autoSizeColumns($sheet, $columns);

        return $spreadsheet;
    }

    /**
     * Per-status font colors used inside the day-details cell.
     */
    private const DAY_STATUS_COLORS = [
        'present' => '16A34A',   // green
        'vacation' => '2563EB',  // blue
        'exception' => 'D97706', // orange
        'holiday' => '64748B',   // slate
        'incomplete' => '9333EA', // purple
        'absent' => 'DC2626',    // red
    ];

    /**
     * Write a one-line color legend under the summary so the reader knows what
     * each color in the "تفاصيل الأيام" column means.
     */
    private function writeDayDetailsLegend(Worksheet $sheet, int $row, int $lastColumn): int
    {
        $lastColLetter = Coordinate::stringFromColumnIndex($lastColumn);
        $coord = 'A'.$row;

        $legend = new RichText;
        $legend->createTextRun('دليل الألوان: ')->getFont()->setBold(true);

        $statuses = [
            ['label' => __('shifts::shifts.on_vacation'), 'status' => 'vacation'],
            ['label' => __('shifts::shifts.on_exception'), 'status' => 'exception'],
            ['label' => __('shifts::shifts.official_holiday'), 'status' => 'holiday'],
            ['label' => __('shifts::shifts.incomplete'), 'status' => 'incomplete'],
            ['label' => __('shifts::shifts.absent_short'), 'status' => 'absent'],
        ];

        foreach ($statuses as $i => $entry) {
            if ($i > 0) {
                $legend->createText('  ·  ');
            }

            $run = $legend->createTextRun($entry['label']);
            $color = self::DAY_STATUS_COLORS[$entry['status']] ?? '374151';
            $run->getFont()->setBold(true)->setColor(new Color($color));
        }

        $sheet->setCellValue($coord, $legend);
        $sheet->mergeCells('A'.$row.':'.$lastColLetter.$row);
        $sheet->getStyle('A'.$row.':'.$lastColLetter.$row)->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);

        return $row + 1;
    }

    /**
     * Render the per-day detail list as a colored RichText grouped by status,
     * e.g. "إجازة: 08-03، 08-05 · غياب: 08-07" where each status segment is
     * written in its own color so vacations stand out in Excel.
     *
     * @param  array<int, array{date: string, status: string, label: string}>  $details
     */
    private function formatDayDetails(array $details): RichText
    {
        $richText = new RichText;

        if ($details === []) {
            $richText->createTextRun('—');

            return $richText;
        }

        $grouped = [];
        foreach ($details as $day) {
            $status = $day['status'] ?? 'absent';
            $label = $day['label'] ?? __('shifts::shifts.on_exception');
            $shortDate = strlen((string) $day['date']) === 10 ? substr((string) $day['date'], 5) : $day['date'];
            $grouped[$status]['label'] = $label;
            $grouped[$status]['dates'][] = $shortDate;
        }

        $first = true;
        foreach ($grouped as $status => $group) {
            if (! $first) {
                $richText->createText(' · ');
            }
            $first = false;

            $color = self::DAY_STATUS_COLORS[$status] ?? '374151';

            // Bold colored label run (e.g. "إجازة:")
            $labelRun = $richText->createTextRun($group['label'].': ');
            $labelRun->getFont()->setBold(true)->setColor(new Color($color));

            // Colored dates run (e.g. "08-03، 08-05")
            $datesRun = $richText->createTextRun(implode('، ', $group['dates']));
            $datesRun->getFont()->setColor(new Color($color));
        }

        return $richText;
    }
}
