<?php

namespace Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Companies\Http\Resources\CompanyResource;
use Modules\Companies\Services\CompanyService;

class CompaniesController extends Controller
{
    public function __construct(
        private CompanyService $companyService
    ) {}

    /**
     * Display a listing of companies.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view-companies');

        return Inertia::render('Companies/Index', [
            'filters' => fn () => $request->only(['search', 'status', 'is_default']),
            'companies' => fn () => CompanyResource::collection(
                $this->companyService->getAllCompanies(
                    $request->only(['search', 'status', 'is_default'])
                )
            ),
        ]);
    }

    /**
     * Show the form for creating a new company.
     */
    public function create(): Response
    {
        $this->authorize('create-companies');

        return Inertia::render('Companies/Create');
    }

    /**
     * Store a newly created company.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create-companies');

        $this->companyService->createCompany($request->all());

        return redirect()->route('companies.index')
            ->with('success', __('companies.created_successfully'));
    }

    /**
     * Display the specified company.
     */
    public function show(int $id): Response
    {
        $this->authorize('view-companies');

        $company = $this->companyService->getCompanyById($id);

        if (! $company) {
            abort(404);
        }

        return Inertia::render('Companies/Show', [
            'company' => fn () => new CompanyResource($company),
        ]);
    }

    /**
     * Show the form for editing the specified company.
     */
    public function edit(int $id): Response
    {
        $this->authorize('edit-companies');

        $company = $this->companyService->getCompanyById($id);

        if (! $company) {
            abort(404);
        }

        return Inertia::render('Companies/Edit', [
            'company' => fn () => new CompanyResource($company),
        ]);
    }

    /**
     * Update the specified company.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorize('edit-companies');

        $company = $this->companyService->getCompanyById($id);

        if (! $company) {
            abort(404);
        }

        $this->companyService->updateCompany($company, $request->all());

        return redirect()->route('companies.index')
            ->with('success', __('companies.updated_successfully'));
    }

    /**
     * Remove the specified company from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('delete-companies');

        $company = $this->companyService->getCompanyById($id);

        if (! $company) {
            abort(404);
        }

        $this->companyService->deleteCompany($company);

        return redirect()->route('companies.index')
            ->with('success', __('companies.deleted_successfully'));
    }

    /**
     * Search companies by keyword.
     */
    public function search(Request $request): Response
    {
        $this->authorize('view-companies');

        return Inertia::render('Companies/Index', [
            'filters' => fn () => $request->only(['search']),
            'companies' => fn () => CompanyResource::collection(
                $this->companyService->getAllCompanies(
                    $request->only(['search'])
                )
            ),
        ]);
    }
}
