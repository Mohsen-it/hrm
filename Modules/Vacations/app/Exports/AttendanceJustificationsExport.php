<?php

namespace Modules\Vacations\Exports;

use App\Services\ExcelExportService;
use Illuminate\Support\Collection;
use Modules\Vacations\Models\AttendanceJustificationRequest;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Excel report for attendance-justification requests. */
class AttendanceJustificationsExport
{
    private ExcelExportService $exporter;

    /** @param Collection<int, AttendanceJustificationRequest> $requests */
    public function __construct(private Collection $requests)
    {
        $this->exporter = app(ExcelExportService::class);
    }

    /** Build the downloadable workbook. */
    public function build(): Spreadsheet
    {
        $sheet = $this->exporter->create()->getActiveSheet();
        $this->exporter->setupSheet($sheet, 'التبريرات');
        $row = $this->exporter->writeTitle($sheet, 'تقرير تبريرات الحضور', 'طلبات بصمة الدخول والخروج والتأخر', 1, 8);
        $row = $this->exporter->writeSummary($sheet, ['إجمالي الطلبات' => $this->requests->count(), 'طلبات التأخر' => $this->requests->where('late_arrival', true)->count(), 'إجمالي دقائق التأخر' => $this->requests->sum('late_minutes')], ++$row, 8);
        $this->exporter->writeHeaders($sheet, ['#', 'الموظف', 'الرمز', 'التاريخ', 'الوقت', 'النوع', 'دقائق التأخر', 'السبب'], ++$row);
        $columns = ['id' => ['key' => 'id', 'type' => 'integer', 'width' => 8], 'name' => ['key' => 'user.name', 'type' => 'string', 'width' => 26], 'code' => ['key' => 'user.employee_code', 'type' => 'string', 'width' => 16], 'date' => ['key' => 'attendance_date', 'type' => 'date', 'width' => 14, 'format' => 'Y-m-d'], 'time' => ['key' => 'arrival_time', 'type' => 'string', 'width' => 12], 'type' => ['key' => 'types_label', 'type' => 'string', 'width' => 28], 'late' => ['key' => 'late_minutes', 'type' => 'integer', 'width' => 16], 'reason' => ['key' => 'reason', 'type' => 'string', 'width' => 40]];
        $this->exporter->writeRows($sheet, $this->requests->map(function (AttendanceJustificationRequest $item) {
            $item->types_label = implode('، ', array_filter([$item->missing_check_in ? 'بصمة دخول' : null, $item->missing_check_out ? 'بصمة خروج' : null, $item->late_arrival ? 'تأخر' : null])) ?: 'بدون نوع محدد';

            return $item;
        }), $columns, ++$row);
        $this->exporter->autoSizeColumns($sheet, $columns);

        return $sheet->getParent();
    }
}
