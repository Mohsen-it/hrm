<?php

namespace Modules\Users\Exports;

use App\Services\ExcelExportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Users\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * UsersExport
 *
 * تصدير قائمة الموظفين بتنسيق احترافي مع دعم RTL والعربية.
 */
class UsersExport
{
    private ExcelExportService $exporter;

    /**
     * @param  Collection<int, User>|LengthAwarePaginator  $users
     */
    public function __construct(
        private Collection|LengthAwarePaginator $users,
        private array $filters = [],
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'قائمة الموظفين');

        $data = $this->users instanceof LengthAwarePaginator
            ? $this->users->getCollection()
            : $this->users;

        $lastColumn = 12;
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'قائمة الموظفين',
            'تقرير شامل لجميع الموظفين في النظام',
            1,
            $lastColumn
        );

        $currentRow++;

        $summary = [
            'إجمالي الموظفين' => $data->count(),
        ];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $headers = [
            '#', 'الاسم', 'رمز الموظف', 'البريد', 'الهاتف',
            'الشركة', 'الفرع', 'القسم', 'الوظيفة', 'الدرجة',
            'تاريخ التعيين', 'الحالة',
        ];
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'name' => ['key' => 'name', 'type' => 'string', 'width' => 25],
            'code' => ['key' => 'employee_code', 'type' => 'string', 'width' => 15],
            'email' => ['key' => 'email', 'type' => 'string', 'width' => 25],
            'phone' => ['key' => 'phone', 'type' => 'string', 'width' => 15],
            'company' => ['key' => 'company.company_name', 'type' => 'string', 'width' => 20],
            'branch' => ['key' => 'branch.branch_name', 'type' => 'string', 'width' => 20],
            'department' => ['key' => 'department.department_name', 'type' => 'string', 'width' => 20],
            'position' => ['key' => 'position.position_name', 'type' => 'string', 'width' => 20],
            'grade' => ['key' => 'grade.grade_name', 'type' => 'string', 'width' => 15],
            'hire_date' => ['key' => 'hire_date', 'type' => 'date', 'width' => 15, 'format' => 'Y-m-d'],
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

    public function toResponse(): array
    {
        return $this->exporter->toResponse($this->build(), 'users');
    }
}
