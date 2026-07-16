<?php

namespace Modules\Companies\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Companies\Models\Company;
use Modules\Companies\Repositories\CompanyRepository;

class CompanyService
{
    public function __construct(
        private CompanyRepository $repository
    ) {}

    /**
     * Get all companies with request filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllCompanies(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Get all active companies.
     *
     * @return Collection<int, Company>
     */
    public function getActiveCompanies(): Collection
    {
        return $this->repository->getActive();
    }

    /**
     * Find a company by its primary key.
     */
    public function getCompanyById(int $id): ?Company
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new company.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createCompany(array $data): Company
    {
        $validated = $this->validateCompanyData($data);

        if (isset($validated['logo']) && $validated['logo'] instanceof UploadedFile) {
            $validated['logo'] = $this->uploadLogo($validated['logo']);
        }

        $company = $this->repository->create($validated);

        if ($company->is_default) {
            $this->ensureOnlyOneDefault($company);
        }

        return $company;
    }

    /**
     * Update the given company.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateCompany(Company $company, array $data): Company
    {
        $validated = $this->validateCompanyData($data, $company->id);

        if (isset($validated['logo']) && $validated['logo'] instanceof UploadedFile) {
            $validated['logo'] = $this->uploadLogo($validated['logo'], $company->logo);
        }

        $company = $this->repository->update($company, $validated);

        if ($company->is_default) {
            $this->ensureOnlyOneDefault($company);
        }

        return $company;
    }

    /**
     * Soft delete the given company.
     */
    public function deleteCompany(Company $company): bool
    {
        return $this->repository->delete($company);
    }

    /**
     * Validate company data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateCompanyData(array $data, ?int $ignoreId = null): array
    {
        $rules = [
            'company_code' => ['required', 'string', 'max:50', 'unique:companies,company_code'.($ignoreId ? ','.$ignoreId : '')],
            'company_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:companies,email'.($ignoreId ? ','.$ignoreId : '')],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:10'],
            'state' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'description' => ['nullable', 'string'],
            'established_date' => ['nullable', 'date'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'commercial_number' => ['nullable', 'string', 'max:50'],
            'is_default' => ['boolean'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];

        return Validator::make($data, $rules)->validate();
    }

    /**
     * Upload a company logo and optionally remove the old one.
     */
    protected function uploadLogo(UploadedFile $logo, ?string $oldLogo = null): string
    {
        $path = $logo->store('companies/logos', 'public');

        if ($oldLogo) {
            Storage::disk('public')->delete($oldLogo);
        }

        return $path;
    }

    /**
     * Ensure only the given company is marked as default.
     */
    protected function ensureOnlyOneDefault(Company $company): void
    {
        $this->repository->query()
            ->where('id', '!=', $company->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
