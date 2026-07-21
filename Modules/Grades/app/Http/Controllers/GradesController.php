<?php

namespace Modules\Grades\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ExcelExportable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Companies\Services\CompanyService;
use Modules\Grades\Http\Requests\StoreGradeRequest;
use Modules\Grades\Http\Requests\UpdateGradeRequest;
use Modules\Grades\Http\Resources\GradeResource;
use Modules\Grades\Services\GradeService;

class GradesController extends Controller
{
    use ExcelExportable;

    public function __construct(
        private GradeService $gradeService,
        private CompanyService $companyService
    ) {}

    /**
     * Display a listing of grades.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-grades');

        return Inertia::render('Grades/Index', [
            'filters' => fn () => $request->only(['search', 'status', 'company_id', 'level']),
            'grades' => fn () => GradeResource::collection(
                $this->gradeService->getAllGrades(
                    $request->only(['search', 'status', 'company_id', 'level'])
                )
            ),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
        ]);
    }

    /**
     * Show the form for creating a new grade.
     */
    public function create(): Response
    {
        $this->authorize('create-grades');

        return Inertia::render('Grades/Create', [
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
        ]);
    }

    /**
     * Store a newly created grade.
     */
    public function store(StoreGradeRequest $request): RedirectResponse
    {
        $this->gradeService->createGrade($request->validated());

        return redirect()->route('grades.index')
            ->with('success', __('grades.created_successfully'));
    }

    /**
     * Display the specified grade.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-grades');

        $grade = $this->gradeService->getGradeById($id);

        if (! $grade) {
            abort(404);
        }

        return Inertia::render('Grades/Show', [
            'grade' => fn () => new GradeResource($grade),
        ]);
    }

    /**
     * Show the form for editing the specified grade.
     */
    public function edit(int $id): Response
    {
        $this->authorize('edit-grades');

        $grade = $this->gradeService->getGradeById($id);

        if (! $grade) {
            abort(404);
        }

        return Inertia::render('Grades/Edit', [
            'grade' => fn () => new GradeResource($grade),
            'companies' => fn () => $this->companyService->getActiveCompanies()
                ->map(fn ($c) => ['id' => $c->id, 'company_name' => $c->company_name]),
        ]);
    }

    /**
     * Update the specified grade.
     */
    public function update(UpdateGradeRequest $request, int $id): RedirectResponse
    {
        $grade = $this->gradeService->getGradeById($id);

        if (! $grade) {
            abort(404);
        }

        $this->gradeService->updateGrade($grade, $request->validated());

        return redirect()->route('grades.index')
            ->with('success', __('grades.updated_successfully'));
    }

    /**
     * Remove the specified grade from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-grades');

        $grade = $this->gradeService->getGradeById($id);

        if (! $grade) {
            abort(404);
        }

        $this->gradeService->deleteGrade($grade);

        return redirect()->route('grades.index')
            ->with('success', __('grades.deleted_successfully'));
    }

    /**
     * Export grades to Excel.
     */
    public function export(Request $request)
    {
        $this->authorize('view-grades');

        $grades = $this->gradeService->getAllGrades(
            $request->only(['search', 'status', 'company_id', 'level']),
            10000
        );

        $headers = ['#', 'رمز الدرجة', 'اسم الدرجة', 'المستوى', 'الشركة', 'الوصف', 'الحالة'];
        $columns = [
            'index' => ['key' => 'id', 'type' => 'integer', 'width' => 8],
            'code' => ['key' => 'grade_code', 'type' => 'string', 'width' => 15],
            'name' => ['key' => 'grade_name', 'type' => 'string', 'width' => 25],
            'level' => ['key' => 'level', 'type' => 'integer', 'width' => 10],
            'company' => ['key' => 'company.company_name', 'type' => 'string', 'width' => 25],
            'description' => ['key' => 'description', 'type' => 'string', 'width' => 35],
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

        return $this->quickExcelExport('قائمة الدرجات', $headers, $grades->getCollection(), $columns, 'grades');
    }
}
