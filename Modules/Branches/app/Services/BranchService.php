<?php

namespace Modules\Branches\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Branches\Models\Branch;
use Modules\Branches\Repositories\BranchRepository;

class BranchService
{
    /**
     * Versioned to bypass legacy cache entries that serialized Eloquent models.
     */
    private const ACTIVE_BRANCHES_CACHE_KEY = 'lookup:branches:active:v2';

    private const COMPANY_BRANCHES_CACHE_PREFIX = 'lookup:branches:company:v2:';

    private const LOOKUP_CACHE_TTL = 3600;

    public function __construct(
        private BranchRepository $repository
    ) {}

    /**
     * Get all branches with optional filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllBranches(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Get all active branches.
     *
     * @return Collection<int, Branch>
     */
    public function getActiveBranches(): Collection
    {
        /** @var array<int, array<string, mixed>> $branches */
        $branches = Cache::remember(
            self::ACTIVE_BRANCHES_CACHE_KEY,
            self::LOOKUP_CACHE_TTL,
            fn (): array => $this->repository->getActive()
                ->map(fn (Branch $branch): array => $branch->getAttributes())
                ->all(),
        );

        return Branch::hydrate($branches);
    }

    /**
     * Get all branches belonging to a specific company.
     *
     * @return Collection<int, Branch>
     */
    public function getBranchesByCompany(int $companyId): Collection
    {
        /** @var array<int, array<string, mixed>> $branches */
        $branches = Cache::remember(
            self::companyBranchesCacheKey($companyId),
            self::LOOKUP_CACHE_TTL,
            fn (): array => $this->repository->getByCompany($companyId)
                ->map(fn (Branch $branch): array => $branch->getAttributes())
                ->all(),
        );

        return Branch::hydrate($branches);
    }

    /**
     * Find a branch by its primary key.
     */
    public function getBranchById(int $id): ?Branch
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new branch.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createBranch(array $data): Branch
    {
        $validated = $this->validateBranchData($data);

        $branch = $this->repository->create($validated);

        if ($branch->is_main) {
            $this->ensureOnlyOneMain($branch);
        }

        $this->forgetBranchLookupCaches($branch->company_id);

        return $branch;
    }

    /**
     * Update the given branch.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateBranch(Branch $branch, array $data): Branch
    {
        $previousCompanyId = $branch->company_id;
        $validated = $this->validateBranchData($data, $branch->id);

        $branch = $this->repository->update($branch, $validated);

        if ($branch->is_main) {
            $this->ensureOnlyOneMain($branch);
        }

        $this->forgetBranchLookupCaches($previousCompanyId, $branch->company_id);

        return $branch;
    }

    /**
     * Soft delete the given branch.
     */
    public function deleteBranch(Branch $branch): bool
    {
        $deleted = $this->repository->delete($branch);

        if ($deleted) {
            $this->forgetBranchLookupCaches($branch->company_id);
        }

        return $deleted;
    }

    /**
     * Validate branch data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateBranchData(array $data, ?int $ignoreId = null): array
    {
        $rules = [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'branch_code' => [
                'required', 'string', 'max:50',
                'unique:branches,branch_code,'
                    .($ignoreId ? $ignoreId.',id,company_id,'.($data['company_id'] ?? 0) : 'NULL,id,company_id,'.($data['company_id'] ?? 0)),
            ],
            'branch_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:10'],
            'state' => ['nullable', 'string', 'max:20'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'manager_phone' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'is_main' => ['boolean'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];

        return Validator::make($data, $rules)->validate();
    }

    /**
     * Ensure only the given branch is marked as main within its company.
     */
    protected function ensureOnlyOneMain(Branch $branch): void
    {
        $this->repository->query()
            ->where('id', '!=', $branch->id)
            ->where('company_id', $branch->company_id)
            ->where('is_main', true)
            ->update(['is_main' => false]);
    }

    /**
     * Invalidate branch lookup caches affected by a write.
     */
    private function forgetBranchLookupCaches(int ...$companyIds): void
    {
        Cache::forget(self::ACTIVE_BRANCHES_CACHE_KEY);
        Cache::forget('lookup:branches:active');

        foreach (array_unique($companyIds) as $companyId) {
            Cache::forget(self::companyBranchesCacheKey($companyId));
            Cache::forget('lookup:branches:company:'.$companyId);
        }
    }

    /**
     * Build the cache key for a company's branches.
     */
    private static function companyBranchesCacheKey(int $companyId): string
    {
        return self::COMPANY_BRANCHES_CACHE_PREFIX.$companyId;
    }
}
