<?php

namespace Modules\Backups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupAuditLog extends Model
{
    protected $table = 'backup_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'backup_run_id',
        'restore_attempt_id',
        'event',
        'metadata',
        'user_id',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function backupRun(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class, 'backup_run_id');
    }

    public function restoreAttempt(): BelongsTo
    {
        return $this->belongsTo(BackupRestoreAttempt::class, 'restore_attempt_id');
    }
}
