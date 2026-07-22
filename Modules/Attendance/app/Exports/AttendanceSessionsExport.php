<?php

namespace Modules\Attendance\Exports;

use App\Services\ExcelExportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Attendance\Models\AttendanceSession;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * AttendanceSessionsExport
 *
 * تصدير جلسات الحضور (check-in / check-out).
 */
class AttendanceSessionsExport
{
    private ExcelExportService $exporter;

    /**
     * @param  Collection<int, AttendanceSession>|LengthAwarePaginator  $sessions
     */
    public function __construct(
        private Collection|LengthAwarePaginator $sessions,
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'جلسات الحضور');

        $data = $this->sessions instanceof LengthAwarePaginator
            ? $this->sessions->getCollection()
            : $this->sessions;

        $lastColumn = 12;
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'تقرير جلسات الحضور والانصراف',
            'تقرير تفصيلي لجميع جلسات الدخول والخروج',
            1,
            $lastColumn
        );

        $currentRow++;
        $summary = [
            'إجمالي الجلسات' => $data->count(),
            'الجلسات المكتملة' => $data->filter(fn ($s) => $s->check_in_at && $s->check_out_at)->count(),
            'الجلسات المفتوحة' => $data->filter(fn ($s) => $s->check_in_at && ! $s->check_out_at)->count(),
        ];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $headers = [
            '#', 'الموظف', 'رمز الموظف', 'الوردية',
            'التاريخ', 'وقت الدخول', 'وقت الخروج',
            'دقائق التأخير', 'دقائق الخروج المبكر', 'ساعات العمل',
            'المصدر', 'الحالة',
        ];
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'user' => ['key' => 'user.name', 'type' => 'string', 'width' => 25],
            'code' => ['key' => 'user.employee_code', 'type' => 'string', 'width' => 15],
            'shift' => ['key' => 'shift.shift_name', 'type' => 'string', 'width' => 15],
            'date' => ['key' => 'attendance_date', 'type' => 'date', 'width' => 14, 'format' => 'Y-m-d'],
            'check_in' => ['key' => 'check_in_at', 'type' => 'datetime', 'width' => 20, 'format' => 'Y-m-d H:i'],
            'check_out' => ['key' => 'check_out_at', 'type' => 'datetime', 'width' => 20, 'format' => 'Y-m-d H:i'],
            'late' => ['key' => 'late_minutes', 'type' => 'integer', 'width' => 15],
            'early_leave' => ['key' => 'early_leave_minutes', 'type' => 'integer', 'width' => 15],
            'work_hours' => ['key' => 'work_minutes', 'type' => 'float', 'width' => 12, 'decimals' => 1],
            'source' => [
                'key' => 'source',
                'type' => 'status',
                'width' => 12,
                'map' => ['device' => 'جهاز', 'manual' => 'يدوي', 'import' => 'استيراد', 'auto' => 'تلقائي'],
            ],
            'status' => [
                'key' => 'status',
                'type' => 'status',
                'width' => 12,
                'map' => ['open' => 'مفتوح', 'closed' => 'مغلق', 'absent' => 'غائب'],
                'status_color' => [
                    'open' => ['text' => '2563EB', 'bg' => 'DBEAFE'],
                    'closed' => ['text' => '16A34A', 'bg' => 'DCFCE7'],
                    'absent' => ['text' => 'DC2626', 'bg' => 'FEE2E2'],
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
