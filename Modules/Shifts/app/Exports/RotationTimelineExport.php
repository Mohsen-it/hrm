<?php

namespace Modules\Shifts\Exports;

use App\Services\ExcelExportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Shifts\Models\Rotation;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RotationTimelineExport
{
    private const HEADER_ROW = 5;

    private const DATA_START_ROW = 6;

    private ExcelExportService $exporter;

    public function __construct(
        private Rotation $rotation,
        private Collection $groups,
        private array $timeline,
        private string $from,
        private string $to,
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function toBinary(): string
    {
        return $this->exporter->toBinary($this->build());
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'جدول الدورية');

        $this->writeTitle($sheet);
        $this->writeHeaders($sheet);
        $this->writeRows($sheet);
        $this->autoSizeColumns($sheet);

        return $spreadsheet;
    }

    private function writeTitle(Worksheet $sheet): void
    {
        $days = $this->timeline[0]['days'] ?? [];
        $dayCount = count($days);
        $lastCol = Coordinate::stringFromColumnIndex(4 + $dayCount);

        $sheet->setCellValue('A1', 'جدول الدورية - '.$this->rotation->name);
        $sheet->mergeCells('A1:'.$lastCol.'1');
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1:'.$lastCol.'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'النمط: '.implode('', $this->rotation->pattern ?? []).'  |  مدة الدورة: '.$this->rotation->cycle_length.' يوم');
        $sheet->mergeCells('A2:'.$lastCol.'2');

        $sheet->setCellValue('A3', 'من: '.$this->from.'  إلى: '.$this->to);
        $sheet->mergeCells('A3:'.$lastCol.'3');

        $sheet->setCellValue('A4', 'عدد الموظفين: '.count($this->timeline));
        $sheet->mergeCells('A4:'.$lastCol.'4');

        $sheet->getStyle('A2:'.$lastCol.'4')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A2:'.$lastCol.'4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function writeHeaders(Worksheet $sheet): void
    {
        $headers = ['#', 'الموظف', 'رمز الموظف', 'المجموعة'];

        $col = 1;
        foreach ($headers as $label) {
            $coord = Coordinate::stringFromColumnIndex($col).self::HEADER_ROW;
            $sheet->setCellValue($coord, $label);
            $col++;
        }

        $days = $this->timeline[0]['days'] ?? [];
        $arabicMonths = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        foreach ($days as $day) {
            $date = Carbon::parse($day['date']);
            $monthName = $arabicMonths[$date->month];
            $label = $monthName.' '.$date->day;

            $coord = Coordinate::stringFromColumnIndex($col).self::HEADER_ROW;
            $sheet->setCellValue($coord, $label);
            $col++;
        }

        $lastCol = Coordinate::stringFromColumnIndex(3 + $dayCount = count($days));
        $headerRange = 'A'.self::HEADER_ROW.':'.$lastCol.self::HEADER_ROW;

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'FA520F'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        $sheet->getRowDimension(self::HEADER_ROW)->setRowHeight(22);
    }

    private function writeRows(Worksheet $sheet): void
    {
        $row = self::DATA_START_ROW;
        $index = 1;

        $days = $this->timeline[0]['days'] ?? [];
        $dayCount = count($days);

        foreach ($this->timeline as $emp) {
            $sheet->setCellValue('A'.$row, $index);
            $sheet->setCellValue('B'.$row, $emp['employee_name']);
            $sheet->setCellValueExplicit('C'.$row, (string) $emp['employee_code'], DataType::TYPE_STRING);
            $sheet->setCellValue('D'.$row, $emp['group_name']);

            $col = 5;
            foreach ($emp['days'] as $day) {
                $coord = Coordinate::stringFromColumnIndex($col).$row;
                if ($day['is_work_day']) {
                    $sheet->setCellValue($coord, '●');
                    $sheet->getStyle($coord)->applyFromArray([
                        'font' => ['color' => ['rgb' => '16A34A'], 'bold' => true],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['rgb' => 'DCFCE7'],
                        ],
                    ]);
                } else {
                    $sheet->setCellValue($coord, '—');
                    $sheet->getStyle($coord)->applyFromArray([
                        'font' => ['color' => ['rgb' => '9CA3AF']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['rgb' => 'F9FAFB'],
                        ],
                    ]);
                }
                $sheet->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $col++;
            }

            $sheet->getRowDimension($row)->setRowHeight(20);
            $index++;
            $row++;
        }

        $lastRow = max(self::DATA_START_ROW, $row - 1);
        $lastCol = Coordinate::stringFromColumnIndex(4 + $dayCount);
        $bodyRange = 'A'.self::DATA_START_ROW.':'.$lastCol.$lastRow;

        $sheet->getStyle($bodyRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E5E7EB'],
                ],
            ],
        ]);

        // Alternate row banding
        for ($r = self::DATA_START_ROW; $r <= $lastRow; $r++) {
            if (($r - self::DATA_START_ROW) % 2 === 1) {
                $range = 'A'.$r.':'.'D'.$r;
                $sheet->getStyle($range)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => 'F7F2EC'],
                    ],
                ]);
            }
        }

        // Center columns A, C, D
        $sheet->getStyle('A'.self::DATA_START_ROW.':A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C'.self::DATA_START_ROW.':C'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D'.self::DATA_START_ROW.':D'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function autoSizeColumns(Worksheet $sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(10);

        $days = $this->timeline[0]['days'] ?? [];
        for ($i = 0; $i < count($days); $i++) {
            $col = Coordinate::stringFromColumnIndex(5 + $i);
            $sheet->getColumnDimension($col)->setWidth(5);
        }
    }
}
