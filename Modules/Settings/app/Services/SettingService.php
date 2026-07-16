<?php

namespace Modules\Settings\Services;

use Illuminate\Cache\TaggableStore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Settings\Models\Setting;
use Modules\Settings\Repositories\SettingRepository;

/**
 * SettingService — orchestrates CRUD plus group-level operations.
 */
class SettingService
{
    public function __construct(
        private SettingRepository $repository,
    ) {}

    /**
     * Get every setting in the catalogue.
     *
     * @return Collection<int, Setting>
     */
    public function getAllSettings(): Collection
    {
        return $this->repository->getAll();
    }

    /**
     * Get every setting belonging to the supplied group.
     *
     * @return Collection<int, Setting>
     */
    public function getSettingsByGroup(string $group): Collection
    {
        return $this->repository->getByGroup($group);
    }

    /**
     * Get a typed value for the supplied key.
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }

    /**
     * Persist a typed value for the supplied key.
     */
    public function setValue(string $key, mixed $value, array $attributes = []): Setting
    {
        $this->assertValidType($attributes['type'] ?? 'string');
        $attributes['key'] = $key;

        return Setting::set($key, $value, $attributes);
    }

    /**
     * Find a setting by its primary key.
     */
    public function getSettingById(int $id): ?Setting
    {
        return $this->repository->findById($id);
    }

    /**
     * Find a setting by its key.
     */
    public function getSettingByKey(string $key): ?Setting
    {
        return $this->repository->findByKey($key);
    }

    /**
     * Create a new setting.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSetting(array $data): Setting
    {
        $validated = $this->validateData($data);

        $setting = $this->repository->create($validated);
        Setting::forget($setting->key);

        return $setting;
    }

    /**
     * Update the supplied setting.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSetting(Setting $setting, array $data): Setting
    {
        $validated = $this->validateData($data, $setting);

        $setting = $this->repository->update($setting, $validated);
        Setting::forget($setting->key);

        return $setting;
    }

    /**
     * Soft delete the supplied setting.
     */
    public function deleteSetting(Setting $setting): bool
    {
        return $this->repository->delete($setting);
    }

    /**
     * Return the list of known groups.
     *
     * @return array<int, string>
     */
    public function getGroups(): array
    {
        $groups = $this->repository->getGroups();

        return array_values(array_unique(array_merge($groups, ['general', 'attendance', 'branding', 'security', 'integrations'])));
    }

    /**
     * Drop the cache for a given key.
     */
    public function flushCache(string $key): void
    {
        Setting::forget($key);
    }

    /**
     * Drop the cache for an entire group.
     */
    public function flushGroupCache(string $group): void
    {
        Setting::forgetGroup($group);
    }

    /**
     * Drop every cached setting.
     */
    public function flushAll(): void
    {
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            Cache::tags(['settings'])->flush();
        }
    }

    /**
     * Validate the supplied payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateData(array $data, ?Setting $ignore = null): array
    {
        $allowedTypes = ['string', 'int', 'integer', 'float', 'bool', 'boolean', 'json', 'array'];
        $allowedGroups = ['general', 'attendance', 'branding', 'security', 'integrations'];

        $uniqueRule = 'unique:settings,key';
        if ($ignore) {
            $uniqueRule .= ','.$ignore->id;
        }

        $rules = [
            'key' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9_.\-]+$/i', $uniqueRule],
            'value' => ['nullable'],
            'type' => ['nullable', 'string', 'in:'.implode(',', $allowedTypes)],
            'group' => ['nullable', 'string', 'in:'.implode(',', $allowedGroups)],
            'name_ar' => ['nullable', 'string', 'max:200'],
            'name_en' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['nullable', 'boolean'],
            'is_encrypted' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];

        $validated = Validator::make($data, $rules)->validate();

        if (isset($validated['value'])) {
            $type = $validated['type'] ?? 'string';
            $validated['value'] = $this->encode($validated['value'], $type);
        }

        $validated['type'] = $validated['type'] ?? 'string';
        $validated['group'] = $validated['group'] ?? 'general';
        $validated['is_public'] = (bool) ($validated['is_public'] ?? false);
        $validated['is_encrypted'] = (bool) ($validated['is_encrypted'] ?? false);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    /**
     * Encode the supplied value into the canonical string form for storage.
     */
    protected function encode(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool', 'boolean' => $value ? '1' : '0',
            'json', 'array' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            default => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }

    /**
     * Throw when an unsupported type is provided.
     */
    protected function assertValidType(string $type): void
    {
        $allowed = ['string', 'int', 'integer', 'float', 'bool', 'boolean', 'json', 'array'];
        if (! in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException("Unsupported setting type: {$type}");
        }
    }
}
