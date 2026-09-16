<?php

namespace Modules\Backups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRestoreAttempt extends Model
{
    protected $table = 'backup_restore_attempts';

    protected $fillable = [
        'backup_run_id',
        'restore_type',
        'target_database',
        'pre_restore_backup_id',
        'status',
        'started_at',
        'completed_at',
        'error_message',
        'initiated_by',
        'approved_by',
        'reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function backupRun(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class, 'backup_run_id');
    }

    public function preRestoreBackup(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class, 'pre_restore_backup_id');
    }
}
