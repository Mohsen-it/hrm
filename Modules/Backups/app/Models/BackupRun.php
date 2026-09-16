<?php

namespace Modules\Backups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupRun extends Model
{
    protected $table = 'backup_runs';

    protected $fillable = [
        'backup_config_id',
        'type',
        'status',
        'database_driver',
        'database_name',
        'database_server_version',
        'file_path',
        'remote_file_path',
        'file_name',
        'file_size',
        'checksum_algorithm',
        'checksum',
        'compressed',
        'encrypted',
        'verification_status',
        'verification_message',
        'started_at',
        'completed_at',
        'failed_at',
        'error_code',
        'error_message',
        'initiated_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'compressed' => 'boolean',
        'encrypted' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(BackupConfig::class, 'backup_config_id');
    }

    /**
     * @return HasMany<BackupRestoreAttempt, $this>
     */
    public function restoreAttempts(): HasMany
    {
        return $this->hasMany(BackupRestoreAttempt::class, 'backup_run_id');
    }

    /**
     * @return HasMany<BackupAuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(BackupAuditLog::class, 'backup_run_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Monthly backups are created on the 1st, weekly on Sunday, rest daily.
     */
    public function retentionClass(): string
    {
        $day = (int) $this->created_at->format('j');
        if ($day === 1) {
            return 'monthly';
        }
        if ($this->created_at->format('w') === '0') {
            return 'weekly';
        }

        return 'daily';
    }
}
