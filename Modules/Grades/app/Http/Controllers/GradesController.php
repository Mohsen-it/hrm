<?php

namespace Modules\Grades\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Companies\Services\CompanyService;
use Modules\Grades\Http\Resources\GradeResource;
use Modules\Grades\Services\GradeService;

class GradesController extends Controller
{
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
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create-grades');

        $this->gradeService->createGrade($request->all());

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
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorize('edit-grades');

        $grade = $this->gradeService->getGradeById($id);

        if (! $grade) {
            abort(404);
        }

        $this->gradeService->updateGrade($grade, $request->all());

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
}
