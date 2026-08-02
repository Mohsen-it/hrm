<?php

namespace Modules\Shifts\Exports;

use App\Services\ExcelExportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

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
            'أيام الدوام المتوقعة', 'أيام الحضور', 'أيام الغياب', 'أيام الغياب (التواريخ)', 'نسبة الحضور %',
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
            $preparedData[] = $employee;
            $index++;
        }

        $this->exporter->writeRows($sheet, $preparedData, $columns, $currentRow);
        $this->exporter->autoSizeColumns($sheet, $columns);

        return $spreadsheet;
    }
}
