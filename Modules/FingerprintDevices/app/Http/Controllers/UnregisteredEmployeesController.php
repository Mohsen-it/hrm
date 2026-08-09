<?php

namespace Modules\FingerprintDevices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ExcelExportable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Branches\Services\BranchService;
use Modules\Companies\Services\CompanyService;
use Modules\Departments\Services\DepartmentService;
use Modules\FingerprintDevices\Repositories\UnregisteredEmployeeRepository;
use Modules\Subordinations\Services\SubordinationService;
use Modules\Users\Http\Resources\UserIndexResource;

/**
 * UnregisteredEmployeesController — shows employees who have no fingerprint templates registered.
 */
class UnregisteredEmployeesController extends Controller
{
    use ExcelExportable;

    public function __construct(
        private UnregisteredEmployeeRepository $repository,
        private CompanyService $companyService,
        private BranchService $branchService,
        private DepartmentService $departmentService,
        private SubordinationService $subordinationService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('view-fingerprint-devices');

        $filters = $this->cleanFilters($request->only([
            'search', 'company_id', 'branch_id', 'department_id', 'subordination_id', 'status',
        ]));

        return Inertia::render('FingerprintDevices/UnregisteredEmployees', [
            'filters' => fn () => $filters,
            'employees' => fn () => UserIndexResource::collection(
                $this->repository->getAll($filters, $request->input('per_page', 20))
            ),
            'total' => fn () => $this->repository->countAll($filters),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name])
                ->values(),
            'branches' => fn () => $this->branchService->getActiveBranches()
                ->map(fn ($b) => ['id' => $b->id, 'branch_name' => $b->branch_name])
                ->values(),
            'departments' => fn () => $this->departmentService->getAllDepartments([])
                ->getCollection()
                ->map(fn ($d) => ['id' => $d->id, 'department_name' => $d->department_name])
                ->values(),
            'subordinations' => fn () => $this->subordinationService->getActiveSubordinations()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'code' => $s->code,
                    'display_name' => $s->display_name,
                ])
                ->values(),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('view-fingerprint-devices');

        $filters = $this->cleanFilters($request->only([
            'search', 'company_id', 'branch_id', 'department_id', 'subordination_id', 'status',
        ]));

        $employees = $this->repository->getAll($filters, 'all');

        $headers = ['#', 'رمز الموظف', 'الاسم', 'البريد الإلكتروني', 'الشركة', 'الفرع', 'القسم', 'التبعية', 'الحالة'];
        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'employee_code' => ['key' => 'employee_code', 'type' => 'string', 'width' => 15],
            'name' => ['key' => 'name', 'type' => 'string', 'width' => 25],
            'email' => ['key' => 'email', 'type' => 'string', 'width' => 25],
            'company' => ['key' => 'company.company_name', 'type' => 'string', 'width' => 20],
            'branch' => ['key' => 'branch.branch_name', 'type' => 'string', 'width' => 20],
            'department' => ['key' => 'department.department_name', 'type' => 'string', 'width' => 20],
            'subordination' => ['key' => 'subordination.display_name', 'type' => 'string', 'width' => 20],
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

        return $this->quickExcelExport('الموظفون غير المسجلين للبصمة', $headers, $employees->getCollection(), $columns, 'unregistered-employees');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function cleanFilters(array $filters): array
    {
        return array_filter($filters, fn ($v) => $v !== null && $v !== '');
    }
}
