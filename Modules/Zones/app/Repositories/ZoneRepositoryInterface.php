<?php

namespace Modules\Zones\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Zones\Models\Zone;

/**
 * ZoneRepositoryInterface — contract for the zone data-access layer.
 *
 * The interface keeps the controller / service decoupled from the
 * concrete repository implementation so the layer can be swapped
 * (e.g. cached implementation, read replica) without touching the
 * call sites.
 */
interface ZoneRepositoryInterface
{
    /**
     * Return a fresh query builder for the zones table.
     */
    public function query(): Builder;

    /**
     * Get a paginated list of zones filtered by the supplied bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Find a zone by its primary key, eager-loading relations.
     */
    public function findById(int $id): ?Zone;

    /**
     * Find a zone by its unique `code` column.
     */
    public function findByCode(string $code): ?Zone;

    /**
     * Return every active zone, eager-loaded.
     *
     * @return Collection<int, Zone>
     */
    public function getActive(): Collection;

    /**
     * Persist a new zone row.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Zone;

    /**
     * Update an existing zone row.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Zone $zone, array $data): Zone;

    /**
     * Soft delete the supplied zone row.
     */
    public function delete(Zone $zone): bool;

    /**
     * Force the cached branch count to the supplied value.
     */
    public function refreshBranchesCount(int $zoneId): int;

    /**
     * Force the cached employee count to the supplied value.
     */
    public function refreshEmployeesCount(int $zoneId): int;

    /**
     * Force the cached device count to the supplied value.
     */
    public function refreshDevicesCount(int $zoneId): int;
}
