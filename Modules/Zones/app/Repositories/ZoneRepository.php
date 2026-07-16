<?php

namespace Modules\Zones\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Zones\Models\Zone;

/**
 * ZoneRepository — Eloquent data access for `Zone`.
 *
 * The repository is the only layer allowed to talk to the database on
 * behalf of the Zones module. Side effects (cache invalidation,
 * notifications) belong in the service layer.
 */
class ZoneRepository implements ZoneRepositoryInterface
{
    /**
     * @var array<int, string>
     */
    protected array $defaultWith = [
        'company',
        'branches',
    ];

    /**
     * Return a fresh query builder for the zones table.
     */
    public function query(): Builder
    {
        return Zone::query();
    }

    /**
     * Get a paginated list of zones filtered by the supplied bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters(
            $this->query()->with($this->defaultWith)->withCount('branches'),
            $filters
        )->latest()->paginate($perPage);
    }

    /**
     * Find a zone by its primary key, eager-loading relations.
     */
    public function findById(int $id): ?Zone
    {
        return $this->query()->with(array_merge($this->defaultWith, ['users']))->find($id);
    }

    /**
     * Find a zone by its unique `code` column.
     */
    public function findByCode(string $code): ?Zone
    {
        return $this->query()->where('code', $code)->first();
    }

    /**
     * Return every active zone, eager-loaded.
     *
     * @return Collection<int, Zone>
     */
    public function getActive(): Collection
    {
        return $this->query()->active()->with($this->defaultWith)->orderBy('name_ar')->get();
    }

    /**
     * Persist a new zone row.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Zone
    {
        return Zone::create($data);
    }

    /**
     * Update an existing zone row.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Zone $zone, array $data): Zone
    {
        $zone->update($data);

        return $zone->fresh();
    }

    /**
     * Soft delete the supplied zone row.
     */
    public function delete(Zone $zone): bool
    {
        return $zone->delete();
    }

    /**
     * Force the cached branch count to the supplied value.
     */
    public function refreshBranchesCount(int $zoneId): int
    {
        $count = (int) DB::table('zone_branches')
            ->where('zone_id', $zoneId)
            ->count();

        DB::table('zones')->where('id', $zoneId)->update([
            'branches_count' => $count,
            'updated_at' => now(),
        ]);

        return $count;
    }

    /**
     * Force the cached employee count to the supplied value.
     */
    public function refreshEmployeesCount(int $zoneId): int
    {
        $count = (int) DB::table('user_zone')
            ->where('zone_id', $zoneId)
            ->distinct()
            ->count('user_id');

        DB::table('zones')->where('id', $zoneId)->update([
            'employees_count' => $count,
            'updated_at' => now(),
        ]);

        return $count;
    }

    /**
     * Force the cached device count to the supplied value.
     */
    public function refreshDevicesCount(int $zoneId): int
    {
        $count = (int) DB::table('fingerprint_devices as d')
            ->join('zone_branches as zb', 'zb.branch_id', '=', 'd.branch_id')
            ->where('zb.zone_id', $zoneId)
            ->whereNull('d.deleted_at')
            ->count();

        DB::table('zones')->where('id', $zoneId)->update([
            'devices_count' => $count,
            'updated_at' => now(),
        ]);

        return $count;
    }

    /**
     * Apply the supplied filter bag to the supplied query builder.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%");
            });
        });

        $query->when($filters['company_id'] ?? null, function (Builder $q, int $companyId): void {
            $q->where('company_id', $companyId);
        });

        $query->when($filters['zone_type'] ?? null, function (Builder $q, string $type): void {
            $q->where('zone_type', $type);
        });

        $query->when($filters['city'] ?? null, function (Builder $q, string $city): void {
            $q->where('city', $city);
        });

        $query->when($filters['region'] ?? null, function (Builder $q, string $region): void {
            $q->where('region', $region);
        });

        $query->when(isset($filters['is_active']), function (Builder $q) use ($filters): void {
            $q->where('is_active', (bool) $filters['is_active']);
        });

        return $query;
    }
}
