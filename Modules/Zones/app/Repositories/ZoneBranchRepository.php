<?php

namespace Modules\Zones\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Branches\Models\Branch;
use Modules\Zones\Models\Zone;

/**
 * ZoneBranchRepository — manages the `zone_branches` pivot table.
 *
 * The pivot is intentionally simple; the repository only exposes
 * assignment / detachment primitives and small lookups. Statistics
 * recomputation lives on the parent zone repository.
 */
class ZoneBranchRepository
{
    /**
     * Get a fresh query builder for the zone_branches pivot.
     */
    public function query(): Builder
    {
        return DB::table('zone_branches');
    }

    /**
     * Sync (replace) the set of branches attached to the given zone.
     *
     * @param  array<int, array{branch_id:int, is_primary?:bool, priority?:int, notes?:string|null}>  $branches
     */
    public function sync(int $zoneId, array $branches): void
    {
        $now = now();
        $rows = [];

        foreach ($branches as $row) {
            $rows[] = [
                'zone_id' => $zoneId,
                'branch_id' => (int) $row['branch_id'],
                'is_primary' => (bool) ($row['is_primary'] ?? false),
                'priority' => (int) ($row['priority'] ?? 0),
                'notes' => $row['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->query()->where('zone_id', $zoneId)->delete();

        if (! empty($rows)) {
            $this->query()->insert($rows);
        }
    }

    /**
     * Attach a single branch to a zone.
     */
    public function attach(int $zoneId, int $branchId, bool $isPrimary = false, int $priority = 0, ?string $notes = null): bool
    {
        $now = now();

        $affected = $this->query()->insertOrIgnore([
            [
                'zone_id' => $zoneId,
                'branch_id' => $branchId,
                'is_primary' => $isPrimary,
                'priority' => $priority,
                'notes' => $notes,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return $affected > 0;
    }

    /**
     * Detach a single branch from a zone.
     */
    public function detach(int $zoneId, int $branchId): bool
    {
        $deleted = $this->query()
            ->where('zone_id', $zoneId)
            ->where('branch_id', $branchId)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Return the branches attached to the given zone, eager-loaded.
     *
     * @return Collection<int, Branch>
     */
    public function getBranchesForZone(int $zoneId): Collection
    {
        return Branch::query()
            ->join('zone_branches as zb', 'zb.branch_id', '=', 'branches.id')
            ->where('zb.zone_id', $zoneId)
            ->whereNull('branches.deleted_at')
            ->select('branches.*', 'zb.is_primary as pivot_is_primary', 'zb.priority as pivot_priority', 'zb.notes as pivot_notes')
            ->orderByDesc('zb.is_primary')
            ->orderBy('zb.priority')
            ->orderBy('branches.branch_name')
            ->get();
    }

    /**
     * Return the primary branch for the given zone, or null when none is set.
     */
    public function getPrimaryBranchForZone(int $zoneId): ?Branch
    {
        return Branch::query()
            ->join('zone_branches as zb', 'zb.branch_id', '=', 'branches.id')
            ->where('zb.zone_id', $zoneId)
            ->where('zb.is_primary', true)
            ->whereNull('branches.deleted_at')
            ->select('branches.*', 'zb.is_primary as pivot_is_primary', 'zb.priority as pivot_priority')
            ->orderBy('zb.priority')
            ->first();
    }

    /**
     * Return the zones that a branch belongs to.
     *
     * @return Collection<int, Zone>
     */
    public function getZonesForBranch(int $branchId): Collection
    {
        return Zone::query()
            ->join('zone_branches as zb', 'zb.zone_id', '=', 'zones.id')
            ->where('zb.branch_id', $branchId)
            ->whereNull('zones.deleted_at')
            ->select('zones.*', 'zb.is_primary as pivot_is_primary', 'zb.priority as pivot_priority')
            ->get();
    }
}
