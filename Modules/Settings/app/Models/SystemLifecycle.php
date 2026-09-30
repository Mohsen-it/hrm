<?php

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SystemLifecycle — سطر واحد لكل حدث إقلاع/إطفاء للنظام.
 */
class SystemLifecycle extends Model
{
    protected $table = 'system_lifecycles';

    protected $fillable = [
        'event',
        'hostname',
        'pid',
        'php_version',
        'laravel_version',
        'reason',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pid' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeBoots($query)
    {
        return $query->where('event', 'boot');
    }

    public function scopeShutdowns($query)
    {
        return $query->where('event', 'shutdown');
    }
}
