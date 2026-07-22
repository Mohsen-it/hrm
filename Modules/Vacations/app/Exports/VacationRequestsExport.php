<?php

namespace Modules\Vacations\Exports;

use App\Services\ExcelExportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Vacations\Models\UserVacationRequest;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * VacationRequestsExport
 *
 * تصدير طلبات الإجازات.
 */
class VacationRequestsExport
{
    private ExcelExportService $exporter;

    /**
     * @param  Collection<int, UserVacationRequest>|LengthAwarePaginator  $requests
     */
    public function __construct(
        private Collection|LengthAwarePaginator $requests,
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'طلبات الإجازات');

        $data = $this->requests instanceof LengthAwarePaginator
            ? $this->requests->getCollection()
            : $this->requests;

        $lastColumn = 9;
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'تقرير طلبات الإجازات',
            'جميع طلبات الإجازات المقدمة من الموظفين',
            1,
            $lastColumn
        );

        $currentRow++;
        $summary = [
            'إجمالي الطلبات' => $data->count(),
            'قيد الانتظار' => $data->filter(fn ($r) => $r->status === 'pending')->count(),
            'موافق عليها' => $data->filter(fn ($r) => $r->status === 'approved')->count(),
            'مرفوضة' => $data->filter(fn ($r) => $r->status === 'rejected')->count(),
        ];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $headers = [
            '#', 'الموظف', 'رمز الموظف', 'نوع الإجازة',
            'تاريخ البداية', 'تاريخ النهاية', 'الأيام',
            'تاريخ الطلب', 'الحالة',
        ];
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'user' => ['key' => 'user.name', 'type' => 'string', 'width' => 25],
            'code' => ['key' => 'user.employee_code', 'type' => 'string', 'width' => 15],
            'type' => ['key' => 'vacation_type.name_ar', 'type' => 'string', 'width' => 20],
            'start' => ['key' => 'start_date', 'type' => 'date', 'width' => 14, 'format' => 'Y-m-d'],
            'end' => ['key' => 'end_date', 'type' => 'date', 'width' => 14, 'format' => 'Y-m-d'],
            'days' => ['key' => 'days_count', 'type' => 'integer', 'width' => 10],
            'requested_at' => ['key' => 'requested_at', 'type' => 'datetime', 'width' => 20, 'format' => 'Y-m-d H:i'],
            'status' => [
                'key' => 'status',
                'type' => 'status',
                'width' => 12,
                'map' => [
                    'pending' => 'قيد الانتظار',
                    'approved' => 'موافق عليها',
                    'rejected' => 'مرفوضة',
                    'cancelled' => 'ملغية',
                ],
                'status_color' => [
                    'pending' => ['text' => 'D97706', 'bg' => 'FEF3C7'],
                    'approved' => ['text' => '16A34A', 'bg' => 'DCFCE7'],
                    'rejected' => ['text' => 'DC2626', 'bg' => 'FEE2E2'],
                    'cancelled' => ['text' => '6B7280', 'bg' => 'F3F4F6'],
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
