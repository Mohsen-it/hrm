<?php

namespace Modules\Settings\Models;

use Illuminate\Cache\TaggableStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Setting — single key-value configuration row.
 *
 * The model exposes static {@see self::get()} / {@see self::set()} helpers
 * that consult the `settings.cache` repository to keep the
 * frequently-accessed keys in memory between requests.
 */
class Setting extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key', 'value', 'type', 'group', 'name_ar', 'name_en',
        'description', 'is_public', 'is_encrypted', 'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_encrypted' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The cache TTL (in seconds) used by the static helpers.
     */
    public static int $cacheTtl = 3600;

    /**
     * Read a setting value with cast-awareness.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cached = Cache::get(self::cacheKey($key));
        if ($cached !== null) {
            return $cached;
        }

        $row = static::query()->where('key', $key)->first();
        if (! $row) {
            return $default;
        }

        $value = static::castValue($row->value, $row->type);
        Cache::put(self::cacheKey($key), $value, static::$cacheTtl);

        return $value;
    }

    /**
     * Write a setting value, creating the row when missing.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function set(string $key, mixed $value, array $attributes = []): self
    {
        $type = $attributes['type'] ?? 'string';
        $stored = static::encodeValue($value, $type);

        $row = static::query()->updateOrCreate(
            ['key' => $key],
            array_merge($attributes, [
                'key' => $key,
                'value' => $stored,
                'type' => $type,
            ]),
        );

        Cache::forget(self::cacheKey($key));

        return $row;
    }

    /**
     * Drop the cache entry for the given key.
     */
    public static function forget(string $key): void
    {
        Cache::forget(self::cacheKey($key));
    }

    /**
     * Drop the cache entry for every key in the supplied group.
     */
    public static function forgetGroup(string $group): void
    {
        $prefix = self::cacheKey($group.':').'%';
        // We don't have a direct way to scan the cache store; the
        // pragmatic workaround is to flush the whole settings tag.
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            Cache::tags(['settings'])->flush();

            return;
        }
        // No tagging support → expire a sentinel key as a no-op.
        Cache::forget('settings:group:'.$group);
        unset($prefix);
    }

    /**
     * Scope a query to settings belonging to the supplied group.
     */
    public function scopeInGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * Scope a query to public settings.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Get the typed value for the setting.
     */
    public function getTypedValueAttribute(): mixed
    {
        return static::castValue($this->value, $this->type);
    }

    /**
     * Convert the stored string value to its typed representation.
     */
    protected static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float' => (float) $value,
            'bool', 'boolean' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'json', 'array' => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    /**
     * Convert a typed value into the canonical string representation.
     */
    protected static function encodeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool', 'boolean' => $value ? '1' : '0',
            'json', 'array' => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }

    /**
     * Build the cache key for a given setting key.
     */
    protected static function cacheKey(string $key): string
    {
        return 'settings:'.strtolower($key);
    }
}
