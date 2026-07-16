<?php

namespace Modules\Shifts\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Shifts\Models\AuditLog;

class AuditService
{
    /**
     * Log an audit event.
     *
     * @param  string  $action  e.g. 'created', 'updated', 'deleted', 'published', 'regenerated'
     * @param  string  $entityType  e.g. 'ShiftCategory', 'SchedulePeriod'
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(
        string $action,
        string $entityType,
        int $entityId,
        array $oldValues = [],
        array $newValues = []
    ): AuditLog {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /**
     * Log a create action.
     */
    public function logCreated(string $entityType, int $entityId, array $newValues = []): AuditLog
    {
        return $this->log('created', $entityType, $entityId, [], $newValues);
    }

    /**
     * Log an update action.
     */
    public function logUpdated(string $entityType, int $entityId, array $oldValues, array $newValues): AuditLog
    {
        return $this->log('updated', $entityType, $entityId, $oldValues, $newValues);
    }

    /**
     * Log a delete action.
     */
    public function logDeleted(string $entityType, int $entityId, array $oldValues = []): AuditLog
    {
        return $this->log('deleted', $entityType, $entityId, $oldValues, []);
    }

    /**
     * Log a publish action.
     */
    public function logPublished(string $entityType, int $entityId, array $newValues = []): AuditLog
    {
        return $this->log('published', $entityType, $entityId, [], $newValues);
    }

    /**
     * Log a regenerate action.
     */
    public function logRegenerated(string $entityType, int $entityId, array $oldValues, array $newValues): AuditLog
    {
        return $this->log('regenerated', $entityType, $entityId, $oldValues, $newValues);
    }
}
