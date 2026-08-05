<?php

namespace Modules\Attendance\Exports;

use App\Services\ExcelExportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * LateCheckInsExport — تقرير المتأخرين عن تسجيل الدخول.
 */
class LateCheckInsExport
{
    private ExcelExportService $exporter;

    public function __construct(
        private Collection $summaries,
        private string $fromDate = '',
        private string $toDate = '',
        private string $cutoffTime = '',
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'المتأخرون عن تسجيل الدخول');

        $lastColumn = 8;
        $subtitle = "الفترة: {$this->fromDate} إلى {$this->toDate} | cutoff: {$this->cutoffTime}";
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'تقرير المتأخرين عن تسجيل الدخول',
            $subtitle,
            1,
            $lastColumn
        );

        $currentRow++;
        $summary = [
            'عدد المتأخرين' => $this->summaries->count(),
        ];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $headers = ['#', 'الموظف', 'رمز الموظف', 'التاريخ', 'وقت الدخول', 'وقت الحد', 'دقائق التأخير'];
        $headers = array_merge(array_slice($headers, 0, 3), ['القسم'], array_slice($headers, 3));
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'index', 'type' => 'integer', 'width' => 8],
            'user' => ['key' => 'user_name', 'type' => 'string', 'width' => 25],
            'code' => ['key' => 'employee_code', 'type' => 'string', 'width' => 15],
            'department' => ['key' => 'department_name', 'type' => 'string', 'width' => 25],
            'date' => ['key' => 'summary_date', 'type' => 'string', 'width' => 14],
            'check_in' => ['key' => 'first_check_in_at', 'type' => 'string', 'width' => 20],
            'cutoff' => ['key' => 'cutoff_time', 'type' => 'string', 'width' => 12],
            'late' => ['key' => 'late_minutes', 'type' => 'integer', 'width' => 14],
        ];

        $data = $this->summaries->values()->map(fn ($s, $i) => [
            'index' => $i + 1,
            'user_name' => $s->user?->name ?? '—',
            'employee_code' => $s->user?->employee_code ?? '',
            'department_name' => $s->user?->department?->department_name ?? '',
            'summary_date' => $s->attendance_date?->format('Y-m-d') ?? '',
            'first_check_in_at' => $s->check_in_at?->format('H:i') ?? '',
            'cutoff_time' => $this->cutoffTime,
            'late_minutes' => $this->calculateLateMinutes($s->check_in_at, $this->cutoffTime),
        ]);

        $currentRow = $this->exporter->writeRows($sheet, $data, $columns, $currentRow);
        $this->exporter->autoSizeColumns($sheet, $columns);

        return $spreadsheet;
    }

    private function calculateLateMinutes(?string $checkInAt, string $cutoffTime): int
    {
        if (! $checkInAt) {
            return 0;
        }

        $checkIn = Carbon::parse($checkInAt);
        $cutoff = Carbon::parse($checkIn->format('Y-m-d').' '.$cutoffTime);

        if ($checkIn->lte($cutoff)) {
            return 0;
        }

        return (int) $checkIn->diffInMinutes($cutoff);
    }
}
