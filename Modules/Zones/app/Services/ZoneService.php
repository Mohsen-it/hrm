<?php

namespace Modules\Zones\Services;

use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Zones\Enums\ZoneType;
use Modules\Zones\Models\Zone;
use Modules\Zones\Repositories\ZoneRepositoryInterface;

/**
 * ZoneService — business orchestration for the zone catalogue.
 *
 * Responsibilities:
 *  - Validate payloads before they reach the repository.
 *  - Maintain the `branches_count` / `employees_count` / `devices_count`
 *    cached counters on the `zones` row.
 *  - Invalidate the `branches_by_zone_*` cache entries on writes.
 */
class ZoneService
{
    public function __construct(
        private ZoneRepositoryInterface $repository,
    ) {}

    /**
     * Get a paginated list of zones filtered by the supplied bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllZones(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Return every active zone, eager-loaded.
     *
     * @return Collection<int, Zone>
     */
    public function getActiveZones(): Collection
    {
        return $this->repository->getActive();
    }

    /**
     * Return every active zone belonging to the supplied company.
     *
     * @return Collection<int, Zone>
     */
    public function getZonesByCompany(?int $companyId): Collection
    {
        return $this->repository->query()
            ->active()
            ->forCompany($companyId)
            ->orderBy('name_ar')
            ->get();
    }

    /**
     * Find a zone by its primary key.
     */
    public function getZoneById(int $id): ?Zone
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new zone.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createZone(array $data): Zone
    {
        $validated = $this->validateZoneData($data);

        $zone = $this->repository->create($validated);
        $this->refreshCounts($zone->id);

        return $zone->fresh(['company', 'branches']);
    }

    /**
     * Update the given zone.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateZone(Zone $zone, array $data): Zone
    {
        $validated = $this->validateZoneData($data, $zone->id);

        $zone = $this->repository->update($zone, $validated);

        return $zone->fresh(['company', 'branches']);
    }

    /**
     * Soft delete the given zone.
     */
    public function deleteZone(Zone $zone): bool
    {
        $deleted = $this->repository->delete($zone);
        $this->forgetBranchCache($zone->id);

        return $deleted;
    }

    /**
     * Recompute the cached counters and flush the cache for the given zone.
     */
    public function refreshCounts(int $zoneId): Zone
    {
        $this->repository->refreshBranchesCount($zoneId);
        $this->repository->refreshEmployeesCount($zoneId);
        $this->repository->refreshDevicesCount($zoneId);
        $this->forgetBranchCache($zoneId);

        $zone = $this->repository->findById($zoneId);

        if (! $zone) {
            throw new \RuntimeException("Zone [{$zoneId}] not found after refresh.");
        }

        return $zone;
    }

    /**
     * Validate the supplied payload and return the sanitised array.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateZoneData(array $data, ?int $ignoreId = null): array
    {
        $allowed = array_map(fn (ZoneType $t) => $t->value, ZoneType::cases());

        $uniqueRule = 'unique:zones,code';
        if ($ignoreId) {
            $uniqueRule .= ','.$ignoreId;
        }

        $rules = [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'code' => ['required', 'string', 'max:50', $uniqueRule],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'zone_type' => ['nullable', 'string', 'in:'.implode(',', $allowed)],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];

        $validated = Validator::make($data, $rules)->validate();

        $validated['zone_type'] = $validated['zone_type'] ?? ZoneType::Geographic->value;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        return $validated;
    }

    /**
     * Drop every cached lookup keyed by the given zone id.
     */
    protected function forgetBranchCache(int $zoneId): void
    {
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            Cache::tags(['zones'])->flush();

            return;
        }

        Cache::forget("zones:{$zoneId}:branches");
        Cache::forget("zones:{$zoneId}:primary_branch");
    }
}
