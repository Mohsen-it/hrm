<?php

namespace App\Http\Controllers;

use App\Traits\ExcelExportable;
use Illuminate\Http\Response;

/**
 * ExcelExportExampleController
 *
 * مثال شامل على كيفية استخدام خدمة تصدير Excel.
 * يمكن حذف هذا الملف في بيئة الإنتاج.
 */
class ExcelExportExampleController extends Controller
{
    use ExcelExportable;

    /**
     * مثال 1: تصدير سريع بسيط
     */
    public function simpleExport(): Response
    {
        $headers = ['#', 'الاسم', 'البريد الإلكتروني', 'القسم', 'الحالة'];
        $data = collect([
            (object) ['id' => 1, 'name' => 'أحمد محمد', 'email' => 'ahmed@example.com', 'department' => 'الموارد البشرية', 'status' => 'active'],
            (object) ['id' => 2, 'name' => 'محمد علي', 'email' => 'mohammed@example.com', 'department' => 'تقنية المعلومات', 'status' => 'active'],
            (object) ['id' => 3, 'name' => 'فاطمة أحمد', 'email' => 'fatima@example.com', 'department' => 'المالية', 'status' => 'inactive'],
        ]);

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'name' => ['key' => 'name', 'type' => 'string', 'width' => 25],
            'email' => ['key' => 'email', 'type' => 'string', 'width' => 30],
            'department' => ['key' => 'department', 'type' => 'string', 'width' => 20],
            'status' => [
                'key' => 'status',
                'type' => 'status',
                'width' => 15,
                'map' => ['active' => 'نشط', 'inactive' => 'غير نشط'],
                'status_color' => [
                    'active' => ['text' => '16A34A', 'bg' => 'DCFCE7'],
                    'inactive' => ['text' => 'DC2626', 'bg' => 'FEE2E2'],
                ],
            ],
        ];

        return $this->quickExcelExport('قائمة الموظفين', $headers, $data, $columns, 'employees');
    }

    /**
     * مثال 2: تصدير مع ملخص في الأعلى
     */
    public function exportWithSummary(): Response
    {
        $summaryData = [
            'إجمالي الموظفين' => 150,
            'الموظفين النشطين' => 120,
            'الموظفين غير النشطين' => 30,
            'نسبة النشطين' => '80%',
        ];

        $headers = ['#', 'الاسم', 'القسم', 'الراتب'];
        $data = collect([
            (object) ['id' => 1, 'name' => 'أحمد محمد', 'department' => 'الموارد البشرية', 'salary' => 5000],
            (object) ['id' => 2, 'name' => 'محمد علي', 'department' => 'تقنية المعلومات', 'salary' => 6000],
        ]);

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'name' => ['key' => 'name', 'type' => 'string', 'width' => 25],
            'department' => ['key' => 'department', 'type' => 'string', 'width' => 20],
            'salary' => ['key' => 'salary', 'type' => 'float', 'width' => 15, 'decimals' => 0],
        ];

        return $this->excelExportWithSummary(
            'تقرير الرواتب',
            'تقرير شهري - يناير 2026',
            $summaryData,
            $headers,
            $data,
            $columns,
            'salary-report'
        );
    }

    /**
     * مثال 3: تصدير مع ملخص في النهاية
     */
    public function exportWithFooterSummary(): Response
    {
        $headers = ['#', 'الموظف', 'أيام العمل', 'أيام الغياب', 'ساعات العمل'];
        $data = collect([
            (object) ['id' => 1, 'name' => 'أحمد محمد', 'work_days' => 22, 'absent_days' => 2, 'hours' => 176],
            (object) ['id' => 2, 'name' => 'محمد علي', 'work_days' => 20, 'absent_days' => 4, 'hours' => 160],
            (object) ['id' => 3, 'name' => 'فاطمة أحمد', 'work_days' => 23, 'absent_days' => 1, 'hours' => 184],
        ]);

        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'name' => ['key' => 'name', 'type' => 'string', 'width' => 25],
            'work_days' => ['key' => 'work_days', 'type' => 'integer', 'width' => 15],
            'absent_days' => ['key' => 'absent_days', 'type' => 'integer', 'width' => 15],
            'hours' => ['key' => 'hours', 'type' => 'integer', 'width' => 15],
        ];

        $footerSummary = [
            'label' => 'الإجمالي',
            'values' => [65, 7, 520],
        ];

        return $this->excelExportWithFooterSummary(
            'تقرير الحضور',
            'تقرير شهري - يناير 2026',
            $headers,
            $data,
            $columns,
            $footerSummary,
            'attendance-report'
        );
    }

    /**
     * مثال 4: تصدير مخصص بالكامل
     */
    public function customExport(): Response
    {
        $exporter = $this->excelExporter();
        $spreadsheet = $exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        // إعداد الورقة
        $exporter->setupSheet($sheet, 'تقرير مخصص');

        // كتابة العنوان
        $currentRow = $exporter->writeTitle($sheet, 'تقرير مخصص بالكامل', 'يمكنك التحكم بكل التفاصيل');

        // كتابة رؤوس الأعمدة
        $headers = ['#', 'البيانات', 'القيم'];
        $exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        // كتابة البيانات يدوياً
        $sheet->setCellValue('A'.$currentRow, 1);
        $sheet->setCellValue('B'.$currentRow, 'بيان مخصص');
        $sheet->setCellValue('C'.$currentRow, 'قيمة مخصصة');

        // تصدير الملف
        return $this->downloadExcel($spreadsheet, 'custom-report');
    }
}
