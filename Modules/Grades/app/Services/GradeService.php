<?php

namespace Modules\Grades\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Grades\Models\Grade;
use Modules\Grades\Repositories\GradeRepository;

class GradeService
{
    public function __construct(
        private GradeRepository $repository
    ) {}

    /**
     * Get all grades with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllGrades(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Get all active grades.
     *
     * @return Collection<int, Grade>
     */
    public function getActiveGrades(): Collection
    {
        return $this->repository->getActive();
    }

    /**
     * Get all grades for a specific company.
     *
     * @return Collection<int, Grade>
     */
    public function getGradesByCompany(int $companyId): Collection
    {
        return $this->repository->getByCompany($companyId);
    }

    /**
     * Find a grade by its primary key.
     */
    public function getGradeById(int $id): ?Grade
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new grade.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createGrade(array $data): Grade
    {
        $validated = $this->validateGradeData($data);

        return $this->repository->create($validated);
    }

    /**
     * Update the given grade.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateGrade(Grade $grade, array $data): Grade
    {
        $validated = $this->validateGradeData($data, $grade->id, $grade->company_id);

        return $this->repository->update($grade, $validated);
    }

    /**
     * Soft delete the given grade.
     */
    public function deleteGrade(Grade $grade): bool
    {
        return $this->repository->delete($grade);
    }

    /**
     * Validate grade data. The grade_code must be unique within the same company.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateGradeData(array $data, ?int $ignoreId = null, ?int $companyId = null): array
    {
        $targetCompanyId = $companyId ?? ($data['company_id'] ?? null);

        $rules = [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'grade_code' => [
                'required', 'string', 'max:50',
                $this->uniqueGradeCodeRule($ignoreId, $targetCompanyId),
            ],
            'grade_name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:1', 'max:255'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0', 'gte:min_salary'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];

        return Validator::make($data, $rules)->validate();
    }

    /**
     * Build the unique rule for grade_code scoped to a company.
     *
     * @return array<int, string>
     */
    protected function uniqueGradeCodeRule(?int $ignoreId, ?int $companyId): array
    {
        if (! $companyId) {
            return ['string'];
        }

        $table = 'grades';
        $column = 'grade_code';
        $ignore = $ignoreId ? (string) $ignoreId : 'NULL';

        $wheres = "company_id = {$companyId}";

        return ["unique:{$table},{$column},{$ignore},id,{$wheres}"];
    }
}
