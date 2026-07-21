<?php

namespace App\Traits;

use App\Services\ExcelExportService;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * ExcelExportable
 *
 * Trait لتسهيل تصدير Excel في Controllers.
 */
trait ExcelExportable
{
    /**
     * الحصول على خدمة التصدير
     */
    protected function excelExporter(): ExcelExportService
    {
        return app(ExcelExportService::class);
    }

    /**
     * تصدير ملف Excel كاستجابة للتحميل
     */
    protected function downloadExcel(
        Spreadsheet $spreadsheet,
        string $fileName,
        int $status = 200,
        array $headers = []
    ): Response {
        $binary = $this->excelExporter()->toBinary($spreadsheet);

        return response($binary, $status, array_merge([
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'.xlsx"',
            'Cache-Control' => 'max-age=0',
        ], $headers));
    }

    /**
     * تصدير سريع مع headers جاهزة
     */
    protected function quickExcelExport(
        string $title,
        array $headers,
        iterable $data,
        array $columns,
        string $fileName = 'report'
    ): Response {
        $result = $this->excelExporter()->quickExport($title, $headers, $data, $columns, $fileName);

        return response($result['binary'], 200, [
            'Content-Type' => $result['mimeType'],
            'Content-Disposition' => 'attachment; filename="'.$result['fileName'].'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * تصدير مع ملخص
     */
    protected function excelExportWithSummary(
        string $title,
        string $subtitle,
        array $summaryData,
        array $headers,
        iterable $data,
        array $columns,
        string $fileName = 'report'
    ): Response {
        $result = $this->excelExporter()->exportWithSummary(
            $title,
            $subtitle,
            $summaryData,
            $headers,
            $data,
            $columns,
            $fileName
        );

        return response($result['binary'], 200, [
            'Content-Type' => $result['mimeType'],
            'Content-Disposition' => 'attachment; filename="'.$result['fileName'].'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * تصدير مع ملخص في النهاية
     */
    protected function excelExportWithFooterSummary(
        string $title,
        string $subtitle,
        array $headers,
        iterable $data,
        array $columns,
        array $footerSummary,
        string $fileName = 'report'
    ): Response {
        $result = $this->excelExporter()->exportWithFooterSummary(
            $title,
            $subtitle,
            $headers,
            $data,
            $columns,
            $footerSummary,
            $fileName
        );

        return response($result['binary'], 200, [
            'Content-Type' => $result['mimeType'],
            'Content-Disposition' => 'attachment; filename="'.$result['fileName'].'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
