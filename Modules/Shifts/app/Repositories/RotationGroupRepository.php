<?php

namespace Modules\Shifts\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Shifts\Models\RotationGroup;

class RotationGroupRepository
{
    protected array $defaultWith = ['timeSchedule'];

    public function query(): Builder
    {
        return RotationGroup::query();
    }

    public function getByRotation(int $rotationId): Collection
    {
        return $this->query()
            ->with($this->defaultWith)
            ->where('rotation_id', $rotationId)
            ->ordered()
            ->get();
    }

    public function findById(int $id): ?RotationGroup
    {
        return $this->query()
            ->with([...$this->defaultWith, 'rotation', 'assignments.employee'])
            ->find($id);
    }

    public function create(array $data): RotationGroup
    {
        return RotationGroup::create($data);
    }

    public function update(RotationGroup $group, array $data): RotationGroup
    {
        $group->update($data);

        return $group->fresh([...$this->defaultWith]);
    }

    public function delete(RotationGroup $group): bool
    {
        return $group->delete();
    }

    public function hasActiveAssignments(int $groupId): bool
    {
        return \Modules\Shifts\Models\RotationAssignment::query()
            ->where('rotation_group_id', $groupId)
            ->whereNull('end_date')
            ->exists();
    }

    public function getMaxGroupIndex(int $rotationId): int
    {
        $max = $this->query()
            ->where('rotation_id', $rotationId)
            ->max('group_index');

        return $max !== null ? (int) $max : -1;
    }
}
