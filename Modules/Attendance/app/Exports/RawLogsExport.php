<?php

namespace Modules\Attendance\Exports;

use App\Services\ExcelExportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Attendance\Models\RawAttendanceLog;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * RawLogsExport
 *
 * تصدير سجلات البصم الخام من الأجهزة.
 */
class RawLogsExport
{
    private ExcelExportService $exporter;

    /**
     * @param  Collection<int, RawAttendanceLog>|LengthAwarePaginator  $logs
     */
    public function __construct(
        private Collection|LengthAwarePaginator $logs,
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'سجلات البصم الخام');

        $data = $this->logs instanceof LengthAwarePaginator
            ? $this->logs->getCollection()
            : $this->logs;

        $lastColumn = 9;
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'سجلات البصم الخام',
            'كل سجلات البصمة الواردة من الأجهزة',
            1,
            $lastColumn
        );

        $currentRow++;
        $summary = [
            'إجمالي السجلات' => $data->count(),
            'المعالجة' => $data->filter(fn ($l) => $l->processed)->count(),
            'غير المعالجة' => $data->filter(fn ($l) => ! $l->processed)->count(),
        ];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $headers = [
            '#', 'الموظف', 'رمز الموظف', 'الجهاز', 'وقت البصمة',
            'نوع البصمة', 'نوع التحقق', 'المصدر', 'الحالة',
        ];
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'user' => ['key' => 'user.name', 'type' => 'string', 'width' => 25],
            'code' => ['key' => 'user.employee_code', 'type' => 'string', 'width' => 15],
            'device' => ['key' => 'device.device_name', 'type' => 'string', 'width' => 20],
            'punch_time' => ['key' => 'punch_time', 'type' => 'datetime', 'width' => 20, 'format' => 'Y-m-d H:i:s'],
            'punch_type' => [
                'key' => 'punch_type',
                'type' => 'status',
                'width' => 12,
                'map' => ['in' => 'دخول', 'out' => 'خروج', 'break_in' => 'دخول استراحة', 'break_out' => 'خروج استراحة', 0 => 'دخول', 1 => 'خروج', 2 => 'دخول استراحة', 3 => 'خروج استراحة', 4 => 'دخول إضافي', 5 => 'خروج إضافي'],
            ],
            'verify_type' => [
                'key' => 'verify_type',
                'type' => 'status',
                'width' => 12,
                'map' => ['fingerprint' => 'بصمة', 'face' => 'وجه', 'card' => 'بطاقة', 'password' => 'كلمة مرور', 1 => 'بصمة', 15 => 'وجه', 4 => 'بطاقة'],
            ],
            'source' => [
                'key' => 'source',
                'type' => 'status',
                'width' => 12,
                'map' => ['device' => 'جهاز', 'manual' => 'يدوي', 'import' => 'استيراد'],
            ],
            'processed' => [
                'key' => 'processed',
                'type' => 'status',
                'width' => 12,
                'map' => [true => 'معالج', false => 'غير معالج'],
                'status_color' => [
                    true => ['text' => '16A34A', 'bg' => 'DCFCE7'],
                    false => ['text' => 'D97706', 'bg' => 'FEF3C7'],
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
