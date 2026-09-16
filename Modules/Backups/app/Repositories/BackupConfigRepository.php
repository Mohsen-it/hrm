<?php

namespace Modules\Backups\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Backups\Models\BackupConfig;

class BackupConfigRepository
{
    public function query(): Builder
    {
        return BackupConfig::query();
    }

    public function findById(int $id): ?BackupConfig
    {
        return $this->query()->find($id);
    }

    /**
     * @return Collection<int, BackupConfig>
     */
    public function getEnabledAutomatic(): Collection
    {
        return $this->query()->enabled()->where('frequency', '!=', 'manual_only')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BackupConfig
    {
        return BackupConfig::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BackupConfig $config, array $data): BackupConfig
    {
        $config->update($data);

        return $config->fresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int|string $perPage = 20): LengthAwarePaginator
    {
        return $this->query()->latest()->paginate((int) $perPage);
    }
}
