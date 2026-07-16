<?php

namespace Modules\Shifts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shifts\Models\Rotation;

class RotationRepository
{
    protected array $defaultWith = ['company', 'groups'];

    public function query(): Builder
    {
        return Rotation::query();
    }

    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters(
            $this->query()
                ->with($this->defaultWith)
                ->withCount(['activeAssignments as active_employees_count']),
            $filters
        )
            ->latest()
            ->paginate($perPage);
    }

    public function getAllList(): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id): ?Rotation
    {
        $rotation = $this->query()
            ->with([
                'company',
                'groups' => function ($q): void {
                    $q->with('timeSchedule');
                },
                'assignments.employee',
            ])
            ->withCount('activeAssignments as active_employees_count')
            ->find($id);

        if ($rotation) {
            $groupIds = $rotation->groups->pluck('id')->toArray();
            $groupCounts = \Modules\Shifts\Models\RotationAssignment::query()
                ->whereIn('rotation_group_id', $groupIds)
                ->whereNull('end_date')
                ->selectRaw('rotation_group_id, count(*) as cnt')
                ->groupBy('rotation_group_id')
                ->pluck('cnt', 'rotation_group_id');

            foreach ($rotation->groups as $group) {
                $group->active_employees_count = $groupCounts->get($group->id, 0);
            }
        }

        return $rotation;
    }

    public function create(array $data): Rotation
    {
        return Rotation::create($data);
    }

    public function update(Rotation $rotation, array $data): Rotation
    {
        $rotation->update($data);

        return $rotation->fresh([...$this->defaultWith, 'groups']);
    }

    public function delete(Rotation $rotation): bool
    {
        return $rotation->delete();
    }

    public function hasActiveAssignments(int $rotationId): bool
    {
        return \Modules\Shifts\Models\RotationAssignment::query()
            ->where('rotation_id', $rotationId)
            ->whereNull('end_date')
            ->exists();
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query->when($filters['search'] ?? null, function (Builder $q, string $search): void {
            $q->where(function (Builder $sub) use ($search): void {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        });

        $query->when($filters['company_id'] ?? null, function (Builder $q, int $companyId): void {
            $q->where('company_id', $companyId);
        });

        return $query;
    }
}
