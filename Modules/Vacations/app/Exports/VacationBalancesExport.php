<?php

namespace Modules\Vacations\Exports;

use App\Services\ExcelExportService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Modules\Vacations\Models\VacationType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * VacationBalancesExport
 *
 * تصدير مصفوفة أرصدة الإجازات (موظف × نوع إجازة × سنة) إلى ملف Excel
 * بتنسيق احترافي مع دعم العربية و RTL.
 */
class VacationBalancesExport
{
    private ExcelExportService $exporter;

    /**
     * @param  EloquentCollection<int, VacationType>|Collection<int, VacationType>  $types
     * @param  Collection<int, array<string, mixed>>  $employees
     */
    public function __construct(
        private int $year,
        private EloquentCollection|Collection $types,
        private Collection $employees,
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, __('vacations.vacation_balances'));

        $headers = ['#', __('vacations.employee'), __('vacations.employee_code'), __('vacations.department')];
        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'user' => ['key' => 'user_name', 'type' => 'string', 'width' => 25],
            'code' => ['key' => 'employee_code', 'type' => 'string', 'width' => 15],
            'dept' => ['key' => 'department_name', 'type' => 'string', 'width' => 20],
        ];

        foreach ($this->types as $type) {
            $name = app()->getLocale() === 'en'
                ? ($type->name_en ?: $type->name_ar)
                : ($type->name_ar ?: $type->name_en);
            $headers[] = "{$name} (".__('vacations.entitled_days').')';
            $headers[] = "{$name} (".__('vacations.remaining').')';
            $columns["t{$type->id}_e"] = ['key' => "t{$type->id}_entitled", 'type' => 'integer', 'width' => 12];
            $columns["t{$type->id}_r"] = ['key' => "t{$type->id}_remaining", 'type' => 'integer', 'width' => 12];
        }

        $headers[] = __('vacations.total_entitled');
        $headers[] = __('vacations.total_remaining');
        $columns['total_e'] = ['key' => 'total_entitled', 'type' => 'integer', 'width' => 12];
        $columns['total_r'] = ['key' => 'total_remaining', 'type' => 'integer', 'width' => 12];

        $lastColumn = count($headers);
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            __('vacations.balances_report_title'),
            __('vacations.balances_report_description', ['year' => $this->year]),
            1,
            $lastColumn
        );

        $currentRow++;
        $summary = [
            __('vacations.employees_count') => $this->employees->count(),
            __('vacations.types_count') => $this->types->count(),
        ];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $this->exporter->writeRows($sheet, $this->buildRows(), $columns, $currentRow);

        $this->exporter->autoSizeColumns($sheet, $columns);

        return $spreadsheet;
    }

    public function toBinary(): string
    {
        return $this->exporter->toBinary($this->build());
    }

    /**
     * Transform the matrix payload into flat export rows with per-type
     * entitled / remaining columns plus grand totals.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildRows(): Collection
    {
        return $this->employees->map(function (array $employee): array {
            $row = [
                'id' => $employee['id'],
                'user_name' => $employee['name'],
                'employee_code' => $employee['employee_code'],
                'department_name' => $employee['department_name'],
            ];

            $totalEntitled = 0;
            $totalRemaining = 0;

            foreach ($this->types as $type) {
                $cell = $employee['balances'][$type->id] ?? null;

                $entitled = $cell ? $cell['days_entitled'] : (int) $type->default_days_per_year;
                $remaining = $cell ? $cell['remaining'] : null;

                $row["t{$type->id}_entitled"] = $entitled;
                // Null stays null so ExcelExportService renders the default "—"
                // instead of casting a string to 0 on integer columns.
                $row["t{$type->id}_remaining"] = $remaining;

                $totalEntitled += $entitled;
                $totalRemaining += $remaining ?? 0;
            }

            $row['total_entitled'] = $totalEntitled;
            $row['total_remaining'] = $totalRemaining;

            return $row;
        })->values();
    }
}
