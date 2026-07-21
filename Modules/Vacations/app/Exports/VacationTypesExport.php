<?php

namespace Modules\Vacations\Exports;

use App\Services\ExcelExportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * VacationTypesExport
 *
 * تصدير أنواع الإجازات.
 */
class VacationTypesExport
{
    private ExcelExportService $exporter;

    /**
     * @param  Collection<int, \Modules\Vacations\Models\VacationType>|LengthAwarePaginator  $types
     */
    public function __construct(
        private Collection|LengthAwarePaginator $types,
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'أنواع الإجازات');

        $data = $this->types instanceof LengthAwarePaginator
            ? $this->types->getCollection()
            : $this->types;

        $lastColumn = 11;
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'أنواع الإجازات',
            'تقرير شامل لجميع أنواع الإجازات المعتمدة',
            1,
            $lastColumn
        );

        $currentRow++;
        $summary = [
            'إجمالي الأنواع' => $data->count(),
            'المدفوعة' => $data->filter(fn ($t) => $t->is_paid)->count(),
            'النشطة' => $data->filter(fn ($t) => $t->is_active)->count(),
        ];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $headers = [
            '#', 'الاسم بالعربية', 'الاسم بالإنجليزية', 'الرمز',
            'الحد السنوي', 'الحد الأقصى للطلب', 'الإشعار المسبق',
            'مدفوعة', 'تتطلب موافقة', 'الوصف', 'الحالة',
        ];
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'name_ar' => ['key' => 'name_ar', 'type' => 'string', 'width' => 25],
            'name_en' => ['key' => 'name_en', 'type' => 'string', 'width' => 25],
            'code' => ['key' => 'code', 'type' => 'string', 'width' => 12],
            'default_days' => ['key' => 'default_days_per_year', 'type' => 'integer', 'width' => 12],
            'max_days' => ['key' => 'max_days_per_request', 'type' => 'integer', 'width' => 14],
            'notice' => ['key' => 'advance_notice_days', 'type' => 'integer', 'width' => 14],
            'is_paid' => [
                'key' => 'is_paid',
                'type' => 'status',
                'width' => 10,
                'map' => [true => 'مدفوعة', false => 'غير مدفوعة'],
                'status_color' => [
                    true => ['text' => '16A34A', 'bg' => 'DCFCE7'],
                    false => ['text' => '666666', 'bg' => 'F3F4F6'],
                ],
            ],
            'requires_approval' => [
                'key' => 'requires_approval',
                'type' => 'status',
                'width' => 14,
                'map' => [true => 'نعم', false => 'لا'],
            ],
            'description' => ['key' => 'description', 'type' => 'string', 'width' => 35],
            'is_active' => [
                'key' => 'is_active',
                'type' => 'status',
                'width' => 10,
                'map' => [true => 'نشط', false => 'غير نشط'],
                'status_color' => [
                    true => ['text' => '16A34A', 'bg' => 'DCFCE7'],
                    false => ['text' => 'DC2626', 'bg' => 'FEE2E2'],
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
