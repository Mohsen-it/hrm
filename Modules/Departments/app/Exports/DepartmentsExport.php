<?php

namespace Modules\Departments\Exports;

use App\Services\ExcelExportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Departments\Models\Department;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * DepartmentsExport
 *
 * تصدير قائمة الأقسام.
 */
class DepartmentsExport
{
    private ExcelExportService $exporter;

    /**
     * @param  Collection<int, Department>|LengthAwarePaginator  $departments
     */
    public function __construct(
        private Collection|LengthAwarePaginator $departments,
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'قائمة الأقسام');

        $data = $this->departments instanceof LengthAwarePaginator
            ? $this->departments->getCollection()
            : $this->departments;

        $lastColumn = 7;
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'قائمة الأقسام',
            'تقرير شامل لجميع الأقسام',
            1,
            $lastColumn
        );

        $currentRow++;
        $summary = ['إجمالي الأقسام' => $data->count()];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $headers = ['#', 'رمز القسم', 'اسم القسم', 'الفرع', 'الشركة', 'القسم الأب', 'الحالة'];
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'code' => ['key' => 'department_code', 'type' => 'string', 'width' => 15],
            'name' => ['key' => 'department_name', 'type' => 'string', 'width' => 30],
            'branch' => ['key' => 'branch.branch_name', 'type' => 'string', 'width' => 25],
            'company' => ['key' => 'company.company_name', 'type' => 'string', 'width' => 25],
            'parent' => ['key' => 'parent.department_name', 'type' => 'string', 'width' => 25],
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
