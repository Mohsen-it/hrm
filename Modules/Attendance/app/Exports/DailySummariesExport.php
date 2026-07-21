<?php

namespace Modules\Attendance\Exports;

use App\Services\ExcelExportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * DailySummariesExport
 *
 * تصدير ملخصات الحضور اليومية.
 */
class DailySummariesExport
{
    private ExcelExportService $exporter;

    /**
     * @param  Collection<int, \Modules\Attendance\Models\DailyAttendanceSummary>|LengthAwarePaginator  $summaries
     */
    public function __construct(
        private Collection|LengthAwarePaginator $summaries,
        private string $fromDate = '',
        private string $toDate = '',
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'ملخصات الحضور اليومية');

        $data = $this->summaries instanceof LengthAwarePaginator
            ? $this->summaries->getCollection()
            : $this->summaries;

        $subtitle = 'الفترة الزمنية';
        if ($this->fromDate || $this->toDate) {
            $subtitle .= ': '.($this->fromDate ?: '...').' إلى '.($this->toDate ?: '...');
        }

        $lastColumn = 11;
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'تقرير ملخصات الحضور اليومية',
            $subtitle,
            1,
            $lastColumn
        );

        $currentRow++;
        $totalWork = $data->sum(fn ($s) => (int) ($s->total_work_minutes ?? 0));
        $totalOvertime = $data->sum(fn ($s) => (int) ($s->total_overtime_minutes ?? 0));
        $totalLate = $data->sum(fn ($s) => (int) ($s->late_minutes ?? 0));
        $summary = [
            'عدد السجلات' => $data->count(),
            'إجمالي دقائق العمل' => number_format($totalWork),
            'إجمالي دقائق الإضافي' => number_format($totalOvertime),
            'إجمالي دقائق التأخير' => number_format($totalLate),
        ];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $headers = [
            '#', 'الموظف', 'رمز الموظف', 'التاريخ',
            'أول دخول', 'آخر خروج',
            'دقائق العمل', 'دقائق الإضافي', 'دقائق التأخير', 'دقائق الخروج المبكر',
            'الحالة',
        ];
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'user' => ['key' => 'user.name', 'type' => 'string', 'width' => 25],
            'code' => ['key' => 'user.employee_code', 'type' => 'string', 'width' => 15],
            'date' => ['key' => 'summary_date', 'type' => 'date', 'width' => 14, 'format' => 'Y-m-d'],
            'check_in' => ['key' => 'first_check_in_at', 'type' => 'datetime', 'width' => 20, 'format' => 'Y-m-d H:i'],
            'check_out' => ['key' => 'last_check_out_at', 'type' => 'datetime', 'width' => 20, 'format' => 'Y-m-d H:i'],
            'work' => ['key' => 'total_work_minutes', 'type' => 'integer', 'width' => 14],
            'overtime' => ['key' => 'total_overtime_minutes', 'type' => 'integer', 'width' => 14],
            'late' => ['key' => 'late_minutes', 'type' => 'integer', 'width' => 14],
            'early' => ['key' => 'early_leave_minutes', 'type' => 'integer', 'width' => 14],
            'status' => [
                'key' => 'status',
                'type' => 'status',
                'width' => 12,
                'map' => ['present' => 'حاضر', 'absent' => 'غائب', 'leave' => 'إجازة', 'holiday' => 'عطلة', 'weekend' => 'عطلة أسبوع'],
                'status_color' => [
                    'present' => ['text' => '16A34A', 'bg' => 'DCFCE7'],
                    'absent' => ['text' => 'DC2626', 'bg' => 'FEE2E2'],
                    'leave' => ['text' => '2563EB', 'bg' => 'DBEAFE'],
                    'holiday' => ['text' => 'D97706', 'bg' => 'FEF3C7'],
                    'weekend' => ['text' => '6B7280', 'bg' => 'F3F4F6'],
                ],
            ],
        ];

        $currentRow = $this->exporter->writeRows($sheet, $data, $columns, $currentRow);
        $this->exporter->autoSizeColumns($sheet, $columns);

        return $spreadsheet;
    }

    public function toBinary(): string
    {
        return $this->exporter->toBinary($this->build());
    }
}
