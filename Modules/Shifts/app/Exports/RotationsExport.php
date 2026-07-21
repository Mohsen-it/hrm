<?php

namespace Modules\Shifts\Exports;

use App\Services\ExcelExportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * RotationsExport
 *
 * تصدير قائمة الدوريات مع المجموعات.
 */
class RotationsExport
{
    private ExcelExportService $exporter;

    /**
     * @param  Collection<int, \Modules\Shifts\Models\Rotation>|LengthAwarePaginator  $rotations
     */
    public function __construct(
        private Collection|LengthAwarePaginator $rotations,
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'الدوريات');

        $data = $this->rotations instanceof LengthAwarePaginator
            ? $this->rotations->getCollection()
            : $this->rotations;

        $lastColumn = 9;
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'قائمة الدوريات',
            'تقرير شامل للدوريات ومجموعاتها',
            1,
            $lastColumn
        );

        $currentRow++;
        $summary = [
            'إجمالي الدوريات' => $data->count(),
            'إجمالي المجموعات' => $data->sum(fn ($r) => $r->number_of_groups ?? 0),
        ];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $headers = [
            '#', 'اسم الدورية', 'النمط', 'مدة الدورة',
            'أيام العمل', 'أيام الراحة', 'عدد المجموعات', 'الشركة', 'الوصف',
        ];
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'name' => ['key' => 'name', 'type' => 'string', 'width' => 25],
            'pattern' => ['key' => 'pattern_display', 'type' => 'string', 'width' => 20],
            'cycle_length' => ['key' => 'cycle_length', 'type' => 'integer', 'width' => 14],
            'work_days' => ['key' => 'work_days_count', 'type' => 'integer', 'width' => 12],
            'rest_days' => ['key' => 'rest_days_count', 'type' => 'integer', 'width' => 12],
            'groups' => ['key' => 'number_of_groups', 'type' => 'integer', 'width' => 14],
            'company' => ['key' => 'company.company_name', 'type' => 'string', 'width' => 25],
            'description' => ['key' => 'description', 'type' => 'string', 'width' => 35],
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
