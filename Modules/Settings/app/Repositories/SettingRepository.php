<?php

namespace Modules\Settings\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Settings\Models\Setting;

/**
 * SettingRepository — Eloquent data access for `Setting`.
 */
class SettingRepository
{
    /**
     * Return a fresh query builder.
     */
    public function query(): Builder
    {
        return Setting::query();
    }

    /**
     * Return every setting, ordered by group/sort.
     *
     * @return Collection<int, Setting>
     */
    public function getAll(): Collection
    {
        return $this->query()
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('key')
            ->get();
    }

    /**
     * Return every setting belonging to the supplied group.
     *
     * @return Collection<int, Setting>
     */
    public function getByGroup(string $group): Collection
    {
        return $this->query()
            ->where('group', $group)
            ->orderBy('sort_order')
            ->orderBy('key')
            ->get();
    }

    /**
     * Find a setting by its primary key.
     */
    public function findById(int $id): ?Setting
    {
        return $this->query()->find($id);
    }

    /**
     * Find a setting by its `key` column.
     */
    public function findByKey(string $key): ?Setting
    {
        return $this->query()->where('key', $key)->first();
    }

    /**
     * Persist a new setting row.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Setting
    {
        return Setting::create($data);
    }

    /**
     * Update the supplied setting row.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Setting $setting, array $data): Setting
    {
        $setting->update($data);

        return $setting->fresh();
    }

    /**
     * Delete the supplied setting row.
     */
    public function delete(Setting $setting): bool
    {
        Setting::forget($setting->key);

        return $setting->delete();
    }

    /**
     * Return the list of distinct groups present in the table.
     *
     * @return array<int, string>
     */
    public function getGroups(): array
    {
        return $this->query()
            ->select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->all();
    }
}
