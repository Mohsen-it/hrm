<?php

namespace Modules\Shifts\Exports;

use App\Services\ExcelExportService;
use Illuminate\Support\Collection;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * RotationEmployeesExport
 *
 * تصدير موظفي الدورية مع كامل المعلومات المتوفرة عنهم
 * (البيانات الشخصية، الشركة، الفرع، القسم، الوظيفة، الدرجة، بيانات الإسناد).
 */
class RotationEmployeesExport
{
    private const GENDERS = [
        'male' => 'ذكر',
        'female' => 'أنثى',
    ];

    private const MARITAL_STATUSES = [
        'single' => 'أعزب',
        'married' => 'متزوج',
        'divorced' => 'مطلق',
        'widowed' => 'أرمل',
    ];

    private const EMPLOYMENT_TYPES = [
        'full_time' => 'دوام كامل',
        'part_time' => 'دوام جزئي',
        'contract' => 'عقد',
        'temporary' => 'مؤقت',
        'intern' => 'متدرب',
    ];

    private ExcelExportService $exporter;

    /**
     * @param  Collection<int, RotationAssignment>  $assignments  Assignments with loaded employee relations.
     */
    public function __construct(
        private Rotation $rotation,
        private Collection $assignments,
    ) {
        $this->exporter = app(ExcelExportService::class);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = $this->exporter->create();
        $sheet = $spreadsheet->getActiveSheet();

        $this->exporter->setupSheet($sheet, 'موظفو الدورية');

        $rows = $this->buildRows();

        $headers = [
            '#', 'رمز الموظف', 'اسم الموظف', 'البريد الإلكتروني', 'الهاتف',
            'الرقم الوطني', 'تاريخ الميلاد', 'الجنس', 'الحالة الاجتماعية', 'الجنسية',
            'الشركة', 'الفرع', 'القسم', 'الوظيفة', 'الدرجة',
            'تاريخ التعيين', 'نوع التوظيف', 'المسمى الوظيفي',
            'مجموعة الدورية', 'تاريخ بدء الإسناد', 'الحالة',
        ];

        $lastColumn = count($headers);
        $currentRow = $this->exporter->writeTitle(
            $sheet,
            'موظفو الدورية - '.$this->rotation->name,
            'تقرير شامل لموظفي الدورية مع كامل بياناتهم',
            1,
            $lastColumn
        );

        $currentRow++;

        $summary = [
            'إجمالي الموظفين' => count($rows),
            'عدد المجموعات' => $this->assignments->pluck('rotation_group_id')->unique()->filter()->count(),
        ];
        $currentRow = $this->exporter->writeSummary($sheet, $summary, $currentRow, $lastColumn);
        $currentRow++;

        $this->exporter->writeHeaders($sheet, $headers, $currentRow);
        $currentRow++;

        $columns = [
            'index' => ['key' => 'index', 'type' => 'integer', 'width' => 8],
            'employee_code' => ['key' => 'employee_code', 'type' => 'string', 'width' => 15],
            'employee_name' => ['key' => 'employee_name', 'type' => 'string', 'width' => 28],
            'email' => ['key' => 'email', 'type' => 'string', 'width' => 25],
            'phone' => ['key' => 'phone', 'type' => 'string', 'width' => 15],
            'national_id' => ['key' => 'national_id', 'type' => 'string', 'width' => 16],
            'date_of_birth' => ['key' => 'date_of_birth', 'type' => 'date', 'width' => 13, 'format' => 'Y-m-d'],
            'gender' => [
                'key' => 'gender', 'type' => 'status', 'width' => 10,
                'map' => self::GENDERS,
            ],
            'marital_status' => [
                'key' => 'marital_status', 'type' => 'status', 'width' => 14,
                'map' => self::MARITAL_STATUSES,
            ],
            'nationality' => ['key' => 'nationality', 'type' => 'string', 'width' => 16],
            'company' => ['key' => 'company', 'type' => 'string', 'width' => 22],
            'branch' => ['key' => 'branch', 'type' => 'string', 'width' => 22],
            'department' => ['key' => 'department', 'type' => 'string', 'width' => 22],
            'position' => ['key' => 'position', 'type' => 'string', 'width' => 22],
            'grade' => ['key' => 'grade', 'type' => 'string', 'width' => 16],
            'hire_date' => ['key' => 'hire_date', 'type' => 'date', 'width' => 13, 'format' => 'Y-m-d'],
            'employment_type' => [
                'key' => 'employment_type', 'type' => 'status', 'width' => 14,
                'map' => self::EMPLOYMENT_TYPES,
            ],
            'job_title' => ['key' => 'job_title', 'type' => 'string', 'width' => 22],
            'rotation_group' => ['key' => 'rotation_group', 'type' => 'string', 'width' => 14],
            'assignment_start_date' => ['key' => 'assignment_start_date', 'type' => 'string', 'width' => 14],
            'is_active' => [
                'key' => 'is_active', 'type' => 'status', 'width' => 10,
                'map' => [1 => 'نشط', 0 => 'غير نشط'],
                'status_color' => [
                    1 => ['text' => '16A34A', 'bg' => 'DCFCE7'],
                    0 => ['text' => 'DC2626', 'bg' => 'FEE2E2'],
                ],
            ],
        ];

        $currentRow = $this->exporter->writeRows($sheet, $rows, $columns, $currentRow);
        $this->exporter->autoSizeColumns($sheet, $columns);

        return $spreadsheet;
    }

    public function toBinary(): string
    {
        return $this->exporter->toBinary($this->build());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(): array
    {
        $rows = [];
        $index = 1;

        foreach ($this->assignments as $assignment) {
            $employee = $assignment->employee;

            if (! $employee) {
                continue;
            }

            $rows[] = [
                'index' => $index++,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->full_name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'national_id' => $employee->national_id,
                'date_of_birth' => $employee->date_of_birth,
                'gender' => $employee->gender,
                'marital_status' => $employee->marital_status,
                'nationality' => $employee->nationality,
                'company' => $employee->company?->company_name,
                'branch' => $employee->branch?->branch_name,
                'department' => $employee->department?->department_name,
                'position' => $employee->position?->position_name,
                'grade' => $employee->grade?->grade_name,
                'hire_date' => $employee->hire_date,
                'employment_type' => $employee->employment_type,
                'job_title' => $employee->job_title,
                'rotation_group' => $assignment->rotationGroup?->name,
                'assignment_start_date' => $assignment->start_date?->format('Y-m-d'),
                'is_active' => ($employee->status === 1 && (bool) $employee->is_active_employee) ? 1 : 0,
            ];
        }

        return $rows;
    }
}
