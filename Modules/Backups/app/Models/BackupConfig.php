<?php

namespace Modules\Backups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupConfig extends Model
{
    protected $table = 'backup_configs';

    protected $fillable = [
        'name',
        'is_enabled',
        'frequency',
        'scheduled_time',
        'day_of_week',
        'timezone',
        'database_connection',
        'database_name',
        'include_files',
        'local_disk',
        'remote_disk',
        'retention_daily',
        'retention_weekly',
        'retention_monthly',
        'encryption_enabled',
        'verification_enabled',
        'notify_on_success',
        'notify_on_failure',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'include_files' => 'boolean',
        'encryption_enabled' => 'boolean',
        'verification_enabled' => 'boolean',
        'notify_on_success' => 'boolean',
        'notify_on_failure' => 'boolean',
        'retention_daily' => 'integer',
        'retention_weekly' => 'integer',
        'retention_monthly' => 'integer',
        'day_of_week' => 'integer',
    ];

    /**
     * @return HasMany<BackupRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(BackupRun::class, 'backup_config_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function isAutomatic(): bool
    {
        return $this->frequency !== 'manual_only';
    }
}
