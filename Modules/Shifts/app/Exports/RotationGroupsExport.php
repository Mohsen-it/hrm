<?php

namespace Modules\Shifts\Exports;

use App\Services\ExcelExportService;
use Illuminate\Support\Collection;
use Modules\Shifts\Models\RotationGroup;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * RotationGroupsExport
 *
 * تصدير مجموعات الدوريات.
 */
class RotationGroupsExport
{
    private ExcelExportService $exporter;

    /**
     * @param  Collection<int, RotationGroup>  $groups
     */
    public function __construct(
        private Collection $groups,
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'مجموعات الدوريات');

        $lastColumn = 6;
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'مجموعات الدوريات',
            'كل المجموعات المرتبطة بالدوريات النشطة',
            1,
            $lastColumn
        );

        $currentRow++;
        $summary = ['إجمالي المجموعات' => $this->groups->count()];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $headers = [
            '#', 'الدورية', 'اسم المجموعة', 'رقم المجموعة',
            'تاريخ البداية', 'عدد الموظفين',
        ];
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'rotation' => ['key' => 'rotation.name', 'type' => 'string', 'width' => 25],
            'name' => ['key' => 'name', 'type' => 'string', 'width' => 20],
            'group_index' => ['key' => 'group_index', 'type' => 'integer', 'width' => 14],
            'start_date' => ['key' => 'start_date', 'type' => 'date', 'width' => 15, 'format' => 'Y-m-d'],
            'employees' => ['key' => 'employees_count', 'type' => 'integer', 'width' => 14],
        ];

        $currentRow = $this->exporter->writeRows($sheet, $this->groups, $columns, $currentRow);
        $this->exporter->autoSizeColumns($sheet, $columns);

        return $spreadsheet;
    }

    public function toBinary(): string
    {
        return $this->exporter->toBinary($this->build());
    }
}
