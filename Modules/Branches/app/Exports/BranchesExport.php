<?php

namespace Modules\Branches\Exports;

use App\Services\ExcelExportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * BranchesExport
 *
 * تصدير قائمة الفروع.
 */
class BranchesExport
{
    private ExcelExportService $exporter;

    /**
     * @param  Collection<int, \Modules\Branches\Models\Branch>|LengthAwarePaginator  $branches
     */
    public function __construct(
        private Collection|LengthAwarePaginator $branches,
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'قائمة الفروع');

        $data = $this->branches instanceof LengthAwarePaginator
            ? $this->branches->getCollection()
            : $this->branches;

        $lastColumn = 8;
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'قائمة الفروع',
            'تقرير شامل لجميع الفروع',
            1,
            $lastColumn
        );

        $currentRow++;
        $summary = ['إجمالي الفروع' => $data->count()];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $headers = ['#', 'رمز الفرع', 'اسم الفرع', 'الشركة', 'المدينة', 'الهاتف', 'رئيسي', 'الحالة'];
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'code' => ['key' => 'branch_code', 'type' => 'string', 'width' => 15],
            'name' => ['key' => 'branch_name', 'type' => 'string', 'width' => 30],
            'company' => ['key' => 'company.company_name', 'type' => 'string', 'width' => 25],
            'city' => ['key' => 'city', 'type' => 'string', 'width' => 15],
            'phone' => ['key' => 'phone', 'type' => 'string', 'width' => 15],
            'is_main' => [
                'key' => 'is_main',
                'type' => 'status',
                'width' => 10,
                'map' => [true => 'رئيسي', false => 'فرع'],
                'status_color' => [
                    true => ['text' => 'FA520F', 'bg' => 'FFF3ED'],
                    false => ['text' => '666666', 'bg' => 'F3F4F6'],
                ],
            ],
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

        $currentRow = $this->exporter->writeRows($sheet, $data, $columns, $currentRow);
        $this->exporter->autoSizeColumns($sheet, $columns);

        return $spreadsheet;
    }

    public function toBinary(): string
    {
        return $this->exporter->toBinary($this->build());
    }
}
