<?php

namespace Modules\Shifts\Exports;

use App\Services\ExcelExportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Shifts\Models\Rotation;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * RotationMonthlyRosterExport
 *
 * التصدير الشهري للدورية بنفس تنسيق الجدول الورقي:
 *
 * | نظام العمل | الفئة الأولى | الفئة الثانية | الفئة الثالثة | الفئة الرابعة |
 * | نظام X     | الموظفون... | ...            | ...            | ...            |
 * | نظام العمل | تاريخ دوام الفئة الأولى | ...                           |
 * | نظام X     | تواريخ الدوام... | ...                            |
 *
 * - الأعمدة = مجموعات الدورية (A,B,C,D = الفئات 1-4) مرتبة حسب group_index.
 * - صف الموظفين = الأسماء المسندة لكل مجموعة خلال الشهر.
 * - صف التواريخ = أيام العمل الفعلية لكل مجموعة خلال الشهر (محسوبة من RotationEngine).
 * - ورقة (الكل) + ورقة مستقلة لكل قسم فيه موظفون مسندون.
 */
class RotationMonthlyRosterExport
{
    private ExcelExportService $exporter;

    /**
     * @param  Collection<int, mixed>  $groups  Ordered by group_index.
     * @param  array<int, array<int, string>>  $employeesByGroup  groupId => [names...] (used when $sheets is empty).
     * @param  array<int, array<int, string>>  $workDatesByGroup  groupId => [Y-m-d...].
     * @param  array<int, array{name: string, employeesByGroup: array<int, array<int, string>>}>  $sheets
     */
    public function __construct(
        private Rotation $rotation,
        private Collection $groups,
        private array $employeesByGroup,
        private array $workDatesByGroup,
        private Carbon $month,
        private array $sheets = [],
    ) {
        $this->exporter = app(ExcelExportService::class);
        $this->month = $month->copy()->startOfMonth();
    }

    public function toBinary(): string
    {
        return $this->exporter->toBinary($this->build());
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();

        $sheets = $this->sheets !== []
            ? $this->sheets
            : [['name' => 'الكل', 'employeesByGroup' => $this->employeesByGroup]];

        $usedTabNames = [];
        foreach (array_values($sheets) as $index => $sheetDef) {
            $sheet = $index === 0
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet();

            $tabName = $this->uniqueTabName((string) ($sheetDef['name'] ?? 'ورقة'), $usedTabNames);
            $usedTabNames[] = $tabName;

            $this->exporter->setupSheet($sheet, $tabName);
            $this->buildSheet($sheet, (string) ($sheetDef['name'] ?? ''), (array) ($sheetDef['employeesByGroup'] ?? []));
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  array<int, array<int, string>>  $employeesByGroup
     */
    private function buildSheet(Worksheet $sheet, string $sheetLabel, array $employeesByGroup): void
    {
        $colCount = 1 + max(1, $this->groups->count());
        $lastCol = Coordinate::stringFromColumnIndex($colCount);

        $this->writeTitle($sheet, $lastCol, $sheetLabel);
        $header1Row = 4;
        $employeesRow = 5;
        $header2Row = 6;
        $datesRow = 7;

        $this->writeHeaderRow($sheet, $header1Row, $colCount, false);
        $this->writeEmployeesRow($sheet, $employeesRow, $colCount, $employeesByGroup);
        $this->writeHeaderRow($sheet, $header2Row, $colCount, true);
        $this->writeDatesRow($sheet, $datesRow, $colCount);

        $this->applyTableBorders($sheet, $header1Row, $datesRow, $lastCol);
        $this->sizeColumns($sheet, $colCount);
        $this->setupPrint($sheet, $lastCol, $datesRow);
    }

    private function writeTitle(Worksheet $sheet, string $lastCol, string $sheetLabel = ''): void
    {
        $monthName = $this->arabicMonthName($this->month->month);
        $title = 'جدول مناوبات الدورية - '.$this->rotation->name.' - '.$monthName.' '.$this->month->year;
        if ($sheetLabel !== '' && $sheetLabel !== 'الكل') {
            $title .= ' - '.$sheetLabel;
        }

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:'.$lastCol.'1');
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => '1A1A1A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $ts = $this->rotation->timeSchedule;
        $subtitle = 'النمط: '.$this->rotation->work_days_count.'+'.$this->rotation->rest_days_count
            .'  |  مدة الدورة: '.$this->rotation->cycle_length.' يوم';
        if ($ts) {
            $subtitle .= '  |  جدول الوقت: '.$ts->name
                .'  |  الدوام: '.$this->shortTime($ts->in_time).' - '.$this->shortTime($ts->out_time);
        }

        $sheet->setCellValue('A2', $subtitle);
        $sheet->mergeCells('A2:'.$lastCol.'2');
        $sheet->getRowDimension(2)->setRowHeight(24);
        $sheet->getStyle('A2:'.$lastCol.'2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '444444']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $sheet->setCellValue('A3', 'الشهر: '.$this->month->format('Y-m').'  (من '.$this->month->copy()->startOfMonth()->format('Y-m-d').' إلى '.$this->month->copy()->endOfMonth()->format('Y-m-d').')');
        $sheet->mergeCells('A3:'.$lastCol.'3');
        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getStyle('A3:'.$lastCol.'3')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    /**
     * Header rows: [نظام العمل | الفئة الأولى | ...] or [نظام العمل | تاريخ دوام الفئة الأولى | ...].
     */
    private function writeHeaderRow(Worksheet $sheet, int $row, int $colCount, bool $isDatesHeader): void
    {
        $sheet->setCellValue('A'.$row, 'نظام العمل');

        $col = 2;
        foreach ($this->groups as $group) {
            $label = $isDatesHeader
                ? 'تاريخ دوام الفئة '.$this->categoryLabel($group)
                : 'الفئة '.$this->categoryLabel($group);
            $coord = Coordinate::stringFromColumnIndex($col).$row;
            $sheet->setCellValue($coord, $label.' ('.$group->name.')');
            $col++;
        }

        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $range = 'A'.$row.':'.$lastCol.$row;

        $sheet->getRowDimension($row)->setRowHeight(30);
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'C2410C']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFF7ED']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1A1A1A']],
            ],
        ]);
    }

    /**
     * @param  array<int, array<int, string>>  $employeesByGroup
     */
    private function writeEmployeesRow(Worksheet $sheet, int $row, int $colCount, array $employeesByGroup): void
    {
        $sheet->setCellValue('A'.$row, $this->rotation->name);

        $maxLines = 1;
        $col = 2;
        foreach ($this->groups as $group) {
            $names = $employeesByGroup[$group->id] ?? [];
            $maxLines = max($maxLines, count($names));
            $coord = Coordinate::stringFromColumnIndex($col).$row;
            $sheet->setCellValue($coord, $names !== [] ? implode("\n", $names) : '—');
            $col++;
        }

        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $sheet->getRowDimension($row)->setRowHeight(max(70, min(400, $maxLines * 20 + 14)));
        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['size' => 12, 'color' => ['rgb' => '1A1A1A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
    }

    private function writeDatesRow(Worksheet $sheet, int $row, int $colCount): void
    {
        $sheet->setCellValue('A'.$row, $this->rotation->name);

        $ts = $this->rotation->timeSchedule;
        $shiftLine = $ts ? 'الدوام: '.$this->shortTime($ts->in_time).' - '.$this->shortTime($ts->out_time) : '';

        $col = 2;
        foreach ($this->groups as $group) {
            $dates = $this->workDatesByGroup[$group->id] ?? [];
            $dayNumbers = array_map(fn (string $d) => (int) Carbon::parse($d)->format('j'), $dates);
            sort($dayNumbers);

            if ($dayNumbers === []) {
                $value = '—';
            } else {
                $value = implode(' - ', $dayNumbers)
                    ."\n".'('.$this->month->format('Y/m').' - عدد الأيام: '.count($dayNumbers).')';
                if ($shiftLine !== '') {
                    $value .= "\n".$shiftLine;
                }
            }

            $coord = Coordinate::stringFromColumnIndex($col).$row;
            $sheet->setCellValue($coord, $value);
            $col++;
        }

        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $sheet->getRowDimension($row)->setRowHeight(90);
        $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
            'font' => ['size' => 12, 'color' => ['rgb' => '1A1A1A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
    }

    private function applyTableBorders(Worksheet $sheet, int $firstRow, int $lastRow, string $lastCol): void
    {
        $sheet->getStyle('A'.$firstRow.':'.$lastCol.$lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1A1A1A']],
            ],
        ]);
    }

    private function sizeColumns(Worksheet $sheet, int $colCount): void
    {
        $sheet->getColumnDimension('A')->setWidth(22);
        for ($c = 2; $c <= $colCount; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(32);
        }
    }

    private function setupPrint(Worksheet $sheet, string $lastCol, int $lastRow): void
    {
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(1);
        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getSheetView()->setZoomScale(90);
    }

    /**
     * Sanitize an Excel tab name (max 31 chars, no \ / ? * [ ] :) and keep it unique.
     *
     * @param  array<int, string>  $used
     */
    private function uniqueTabName(string $name, array $used): string
    {
        $clean = trim(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]+/u', '-', $name) ?? '');
        if ($clean === '') {
            $clean = 'ورقة';
        }
        $base = mb_substr($clean, 0, 31);
        $candidate = $base;
        $suffix = 2;
        while (in_array($candidate, $used, true)) {
            $tail = ' ('.$suffix.')';
            $candidate = mb_substr($base, 0, 31 - mb_strlen($tail)).$tail;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Map group to Arabic ordinal like the paper (A→الأولى, B→الثانية, ...).
     */
    private function categoryLabel(mixed $group): string
    {
        $ordinals = ['الأولى', 'الثانية', 'الثالثة', 'الرابعة', 'الخامسة', 'السادسة', 'السابعة', 'الثامنة'];
        $index = (int) ($group->group_index ?? 0);

        return $ordinals[$index] ?? ('رقم '.($index + 1));
    }

    private function arabicMonthName(int $month): string
    {
        return [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ][$month] ?? (string) $month;
    }

    private function shortTime(mixed $time): string
    {
        if (! $time) {
            return '—';
        }

        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i');
        }

        $time = (string) $time;

        if (preg_match('/^(\d{2}:\d{2})/', $time, $m) === 1) {
            return $m[1];
        }

        try {
            return Carbon::parse($time)->format('H:i');
        } catch (\Throwable) {
            return $time;
        }
    }
}
