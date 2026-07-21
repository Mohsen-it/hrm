<?php

namespace Modules\Shifts\Exports;

use App\Services\ExcelExportService;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * SmartAbsenceDailyExport
 *
 * Renders the daily smart-absence report as a fully formatted .xlsx file
 * with full Arabic / RTL support using ExcelExportService.
 */
class SmartAbsenceDailyExport
{
    private ExcelExportService $exporter;

    public function __construct(
        private Carbon $date,
        private int $totalExpected,
        private int $totalAbsent,
        private iterable $absentDetails,
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

        $this->exporter->setupSheet($sheet, 'تقرير الغياب');

        $headers = [
            '#', 'اسم الموظف', 'رمز الموظف', 'المسمى الوظيفي', 'القسم', 'الفرع',
            'المنصب', 'الدرجة الوظيفية', 'رقم الهاتف', 'الدورية', 'مجموعة الدورية',
            'وقت الحضور المتوقع', 'الحالة',
        ];
        $lastColumn = count($headers);

        // كتابة العنوان
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'تقرير الغياب الذكي',
            'التاريخ: '.$this->date->format('Y-m-d'),
            1,
            $lastColumn
        );

        // كتابة الملخص
        $summaryData = [
            'المتوقع' => $this->totalExpected,
            'الغائبون' => $this->totalAbsent,
            'نسبة الحضور' => $this->totalExpected > 0
                ? (int) round((($this->totalExpected - $this->totalAbsent) / $this->totalExpected) * 100).'%'
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
            'expected_in' => ['key' => 'expected_in', 'type' => 'string', 'width' => 14],
            'status' => ['key' => 'status', 'type' => 'string', 'width' => 10],
        ];

        // تحضير البيانات مع الفهرس
        $index = 1;
        $preparedData = [];
        foreach ($this->absentDetails as $employee) {
            $employee->index = $index;
            $employee->status = $this->statusLabel;
            if (! empty($employee->expected_in)) {
                $employee->expected_in = substr((string) $employee->expected_in, 0, 5);
            } else {
                $employee->expected_in = '';
            }
            $preparedData[] = $employee;
            $index++;
        }

        $this->exporter->writeRows($sheet, $preparedData, $columns, $currentRow);
        $this->exporter->autoSizeColumns($sheet, $columns);

        return $spreadsheet;
    }
}
