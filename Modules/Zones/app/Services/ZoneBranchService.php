<?php

namespace Modules\Zones\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Branches\Models\Branch;
use Modules\Zones\Models\Zone;
use Modules\Zones\Repositories\ZoneBranchRepository;

/**
 * ZoneBranchService — orchestrates branch assignment to zones.
 *
 * All public methods that mutate the pivot recompute the cached
 * `branches_count` counter through {@see ZoneService::refreshCounts()}.
 */
class ZoneBranchService
{
    public function __construct(
        private ZoneBranchRepository $repository,
        private ZoneService $zoneService,
    ) {}

    /**
     * Return the branches attached to the given zone.
     *
     * @return Collection<int, Branch>
     */
    public function getBranchesForZone(int $zoneId): Collection
    {
        return $this->repository->getBranchesForZone($zoneId);
    }

    /**
     * Return the primary branch for the given zone, or null.
     */
    public function getPrimaryBranchForZone(int $zoneId): ?Branch
    {
        return $this->repository->getPrimaryBranchForZone($zoneId);
    }

    /**
     * Return the zones that a branch belongs to.
     *
     * @return Collection<int, Zone>
     */
    public function getZonesForBranch(int $branchId): Collection
    {
        return $this->repository->getZonesForBranch($branchId);
    }

    /**
     * Validate and persist a complete branch set for the supplied zone.
     *
     * @param  array<int, mixed>  $branches
     */
    public function syncBranches(int $zoneId, array $branches): void
    {
        $rows = $this->validateBranches($branches);

        DB::transaction(function () use ($zoneId, $rows): void {
            $this->repository->sync($zoneId, $rows);
            $this->zoneService->refreshCounts($zoneId);
        });
    }

    /**
     * Attach a branch to a zone, marking it primary if requested.
     */
    public function attachBranch(int $zoneId, int $branchId, bool $isPrimary = false, int $priority = 0, ?string $notes = null): bool
    {
        $attached = $this->repository->attach($zoneId, $branchId, $isPrimary, $priority, $notes);

        if ($attached) {
            $this->zoneService->refreshCounts($zoneId);
        }

        return $attached;
    }

    /**
     * Detach a branch from a zone.
     */
    public function detachBranch(int $zoneId, int $branchId): bool
    {
        $detached = $this->repository->detach($zoneId, $branchId);

        if ($detached) {
            $this->zoneService->refreshCounts($zoneId);
        }

        return $detached;
    }

    /**
     * Validate the supplied branch payload and return the canonical rows.
     *
     * @param  array<int, mixed>  $branches
     * @return array<int, array<string, mixed>>
     */
    protected function validateBranches(array $branches): array
    {
        $rules = [
            '*' => ['array'],
            '*.branch_id' => ['required', 'integer', 'exists:branches,id'],
            '*.is_primary' => ['nullable', 'boolean'],
            '*.priority' => ['nullable', 'integer'],
            '*.notes' => ['nullable', 'string', 'max:500'],
        ];

        $validated = Validator::make(['rows' => $branches], ['rows' => $rules])->validate();

        $rows = $validated['rows'];

        $primaryCount = 0;
        foreach ($rows as $row) {
            if (! empty($row['is_primary'])) {
                $primaryCount++;
            }
        }

        if ($primaryCount > 1) {
            Validator::make([], [])->after(function ($validator) use ($primaryCount): void {
                $validator->errors()->add('rows', __('zones.only_one_primary_branch', ['count' => $primaryCount]));
            })->validate();
        }

        return $rows;
    }
}
