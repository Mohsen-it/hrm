<?php

namespace Modules\Attendance\Exports;

use App\Services\ExcelExportService;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * LiveAttendanceExport
 *
 * تصدير لقطة الحضور المباشر (الجلسات النشطة + حالات الشذوذ + المختفي).
 */
class LiveAttendanceExport
{
    private ExcelExportService $exporter;

    public function __construct(
        private string $date,
        private Collection $liveSessions,
        private Collection $missingCheckouts,
        private Collection $anomalies,
        private array $health = [],
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'الحضور المباشر');

        $lastColumn = 8;
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'تقرير الحضور المباشر',
            'التاريخ: '.$this->date,
            1,
            $lastColumn
        );

        $currentRow++;
        $summary = [
            'الجلسات النشطة' => $this->liveSessions->count(),
            'حالات الخروج المفقود' => $this->missingCheckouts->count(),
            'حالات الشذوذ' => $this->anomalies->count(),
            'صحة النظام' => $this->health['status'] ?? '—',
        ];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $headers = [
            '#', 'الموظف', 'رمز الموظف', 'وقت الدخول',
            'المتوقع', 'التأخير (د)', 'الحالة', 'ملاحظات',
        ];
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'user' => ['key' => 'user.name', 'type' => 'string', 'width' => 25],
            'code' => ['key' => 'user.employee_code', 'type' => 'string', 'width' => 15],
            'check_in' => ['key' => 'check_in_at', 'type' => 'datetime', 'width' => 20, 'format' => 'H:i'],
            'expected' => ['key' => 'expected_check_in', 'type' => 'string', 'width' => 12],
            'late' => ['key' => 'late_minutes', 'type' => 'integer', 'width' => 12],
            'status' => [
                'key' => 'status',
                'type' => 'status',
                'width' => 12,
                'map' => ['open' => 'مفتوح', 'closed' => 'مغلق', 'late' => 'متأخر', 'absent' => 'غائب'],
                'status_color' => [
                    'open' => ['text' => '2563EB', 'bg' => 'DBEAFE'],
                    'closed' => ['text' => '16A34A', 'bg' => 'DCFCE7'],
                    'late' => ['text' => 'D97706', 'bg' => 'FEF3C7'],
                    'absent' => ['text' => 'DC2626', 'bg' => 'FEE2E2'],
                ],
            ],
            'notes' => ['key' => 'notes', 'type' => 'string', 'width' => 30],
        ];

        $currentRow = $this->exporter->writeRows($sheet, $this->liveSessions, $columns, $currentRow);
        $this->exporter->autoSizeColumns($sheet, $columns);

        return $spreadsheet;
    }

    public function toBinary(): string
    {
        return $this->exporter->toBinary($this->build());
    }
}
