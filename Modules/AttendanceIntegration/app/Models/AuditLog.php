<?php

namespace Modules\AttendanceIntegration\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'attendance_integration_audit_logs';

    protected $fillable = [
        'action',
        'correlation_id',
        'device_id',
        'device_serial',
        'user_id',
        'device_user_id',
        'status',
        'context',
        'payload_snapshot',
        'duration_ms',
        'ip_address',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'payload_snapshot' => 'array',
            'duration_ms' => 'decimal',
            'occurred_at' => 'datetime',
        ];
    }
}
