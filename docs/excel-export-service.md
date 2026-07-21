# ExcelExportService - خدمة تصدير Excel الشاملة

## نظرة عامة

خدمة شاملة لتصدير ملفات Excel بتنسيق احترافي مع دعم كامل للعربية و RTL.

## الميزات

- ✅ دعم RTL كامل
- ✅ تنسيق احترافي مع ألوان المشروع (Mistral)
- ✅ دعم الخطوط العربية (Cairo)
- ✅ تصدير سريع مع headers جاهزة
- ✅ دعم الأعمدة المخصصة
- ✅ دعم الصفوف الملونة
- ✅ دعم الملخصات والتقارير
- ✅ دعم حالات النجاح/الفشل/التحذير

## الاستخدام

### 1. في Controller (باستخدام Trait)

```php
use App\Traits\ExcelExportable;

class CompaniesController extends Controller
{
    use ExcelExportable;

    public function export()
    {
        $headers = ['#', 'الاسم', 'البريد الإلكتروني'];
        $data = User::all();
        
        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'name' => ['key' => 'name', 'type' => 'string', 'width' => 25],
            'email' => ['key' => 'email', 'type' => 'string', 'width' => 30],
        ];

        return $this->quickExcelExport('قائمة الموظفين', $headers, $data, $columns, 'employees');
    }
}
```

### 2. في Controller (بدون Trait)

```php
use App\Services\ExcelExportService;

class CompaniesController extends Controller
{
    public function __construct(
        private ExcelExportService $excelExporter
    ) {}

    public function export()
    {
        $result = $this->excelExporter->quickExport(
            'قائمة الشركات',
            ['#', 'الاسم', 'البريد'],
            Company::all(),
            [
                'index' => ['key' => 'id', 'type' => 'integer'],
                'name' => ['key' => 'name', 'type' => 'string'],
                'email' => ['key' => 'email', 'type' => 'string'],
            ],
            'companies'
        );

        return response($result['binary'], 200, [
            'Content-Type' => $result['mimeType'],
            'Content-Disposition' => 'attachment; filename="' . $result['fileName'] . '"',
        ]);
    }
}
```

### 3. في Module Export Class

```php
use App\Services\ExcelExportService;

class CompaniesExport
{
    private ExcelExportService $exporter;

    public function __construct(private $companies)
    {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'قائمة الشركات');

        // كتابة العنوان
        $currentRow = $this->exporter->writeTitle($sheet, 'قائمة الشركات', 'تقرير شامل');

        // كتابة رؤوس الأعمدة
        $headers = ['#', 'الاسم', 'البريد'];
        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        // كتابة البيانات
        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer'],
            'name' => ['key' => 'name', 'type' => 'string'],
            'email' => ['key' => 'email', 'type' => 'string'],
        ];

        $this->exporter->writeRows($sheet, $this->companies, $columns, $currentRow);

        return $spreadsheet;
    }

    public function toBinary(): string
    {
        return $this->exporter->toBinary($this->build());
    }
}
```

## الأعمدة المدعومة

### الأنواع

```php
$columns = [
    'id' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
    'name' => ['key' => 'name', 'type' => 'string', 'width' => 25],
    'salary' => ['key' => 'salary', 'type' => 'float', 'decimals' => 2],
    'date' => ['key' => 'created_at', 'type' => 'date', 'format' => 'Y-m-d'],
    'datetime' => ['key' => 'created_at', 'type' => 'datetime'],
    'is_active' => ['key' => 'status', 'type' => 'boolean', 'true' => 'نشط', 'false' => 'غير نشط'],
    'status' => ['key' => 'status', 'type' => 'status', 'map' => ['active' => 'نشط', 'inactive' => 'غير نشط']],
];
```

### الألوان

```php
$columns = [
    'status' => [
        'key' => 'status',
        'type' => 'status',
        'map' => ['active' => 'نشط', 'inactive' => 'غير نشط'],
        'status_color' => [
            'active' => ['text' => '16A34A', 'bg' => 'DCFCE7'],
            'inactive' => ['text' => 'DC2626', 'bg' => 'FEE2E2'],
        ],
    ],
];
```

## التقارير المتقدمة

### تقرير مع ملخص في الأعلى

```php
$summaryData = [
    'إجمالي الموظفين' => 150,
    'الموظفين النشطين' => 120,
    'الموظفين غير النشطين' => 30,
];

return $this->excelExportWithSummary(
    'تقرير الموظفين',
    'تقرير شهري - يناير 2026',
    $summaryData,
    $headers,
    $data,
    $columns,
    'employees-report'
);
```

### تقرير مع ملخص في النهاية

```php
$footerSummary = [
    'label' => 'الإجمالي',
    'values' => [150, 120, 30],
];

return $this->excelExportWithFooterSummary(
    'تقرير الحضور',
    'تقرير شهري',
    $headers,
    $data,
    $columns,
    $footerSummary,
    'attendance-report'
);
```

## الألوان المتاحة

| اللون | الكود | الاستخدام |
|-------|-------|----------|
| Primary | FA520F | العنوان الرئيسي |
| Primary Light | FFF3ED | خلفية الملخص |
| Header BG | FA520F | خلفية رؤوس الأعمدة |
| Header Text | FFFFFF | نص رؤوس الأعمدة |
| Row Alt | F7F2EC | الصفوف المتبادلة |
| Success | 16A34A | نص النجاح |
| Success BG | DCFCE7 | خلفية النجاح |
| Danger | DC2626 | نص الخطأ |
| Danger BG | FEE2E2 | خلفية الخطأ |
| Warning | D97706 | نص التحذير |
| Warning BG | FEF3C7 | خلفية التحذير |
| Info | 2563EB | نص المعلومات |
| Info BG | DBEAFE | خلفية المعلومات |

## المتطلبات

- PHP 8.3+
- phpoffice/phpspreadsheet 5.9+
- Laravel 13+

## التثبيت

الخدمة مثبتة مسبقاً في المشروع. فقط استخدمها في Controllers أو Services.

## ملاحظات

- جميع الخصائص تدعم RTL تلقائياً
- الخط الافتراضي هو Cairo (يدعم العربية)
- الأعمدة تحدد عرضها تلقائياً بناءً على المحتوى
- الصفوف المتبادلة تعمل تلقائياً
