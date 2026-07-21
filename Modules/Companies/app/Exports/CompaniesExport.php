<?php

namespace Modules\Companies\Exports;

use App\Services\ExcelExportService;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * CompaniesExport
 *
 * مثال على كيفية استخدام ExcelExportService في Module.
 */
class CompaniesExport
{
    private ExcelExportService $exporter;

    public function __construct(
        private Collection $companies
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    /**
     * بناء ملف Excel
     */
    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'قائمة الشركات');

        $lastColumn = 6;
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'قائمة الشركات',
            'تقرير شامل لجميع الشركات',
            1,
            $lastColumn
        );

        $currentRow++;

        $headers = ['#', 'رمز الشركة', 'اسم الشركة', 'البريد الإلكتروني', 'الهاتف', 'الحالة'];
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'code' => ['key' => 'company_code', 'type' => 'string', 'width' => 15],
            'name' => ['key' => 'company_name', 'type' => 'string', 'width' => 30],
            'email' => ['key' => 'email', 'type' => 'string', 'width' => 25],
            'phone' => ['key' => 'phone', 'type' => 'string', 'width' => 15],
            'status' => [
                'key' => 'status',
                'type' => 'status',
                'width' => 12,
                'map' => [1 => 'نشط', 0 => 'غير نشط'],
                'status_color' => [
                    1 => ['text' => '16A34A', 'bg' => 'DCFCE7'],
                    0 => ['text' => 'DC2626', 'bg' => 'FEE2E2'],
                ],
            ],
        ];

        $currentRow = $this->exporter->writeRows($sheet, $this->companies, $columns, $currentRow);

        $this->exporter->autoSizeColumns($sheet, $columns);

        return $spreadsheet;
    }

    /**
     * تصدير كـ binary
     */
    public function toBinary(): string
    {
        return $this->exporter->toBinary($this->build());
    }

    /**
     * تصدير كـ response
     */
    public function toResponse(): array
    {
        return $this->exporter->toResponse($this->build(), 'companies');
    }
}
