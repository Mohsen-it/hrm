<?php

namespace Modules\Backups\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Backups\Models\BackupRun;

class BackupRunRepository
{
    public function query(): Builder
    {
        return BackupRun::query();
    }

    public function findById(int $id): ?BackupRun
    {
        return $this->query()->with(['config'])->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BackupRun
    {
        return BackupRun::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BackupRun $run, array $data): BackupRun
    {
        $run->update($data);

        return $run->fresh();
    }

    public function delete(BackupRun $run): bool
    {
        return $run->delete();
    }

    public function latestVerified(): ?BackupRun
    {
        return $this->query()->completed()->verified()->latest('id')->first();
    }

    public function latestSuccessful(): ?BackupRun
    {
        return $this->query()->completed()->latest('id')->first();
    }

    public function countVerified(): int
    {
        return $this->query()->completed()->verified()->count();
    }

    /**
     * @return Collection<int, BackupRun>
     */
    public function getCompletedOrdered(string $retentionClass): Collection
    {
        return $this->query()->completed()->latest('id')->get()
            ->filter(fn (BackupRun $run) => $run->retentionClass() === $retentionClass)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int|string $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()->with(['config'])->latest();

        $query->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status));
        $query->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->where('type', $type));
        $query->when($filters['verification_status'] ?? null, fn (Builder $q, $v) => $q->where('verification_status', $v));

        return $query->paginate((int) $perPage);
    }
}
