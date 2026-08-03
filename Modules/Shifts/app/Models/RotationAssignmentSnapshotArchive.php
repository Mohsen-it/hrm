<?php

namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores the lossless, compressed source snapshot for a rotation assignment.
 */
class RotationAssignmentSnapshotArchive extends Model
{
    protected $table = 'att_rotation_assignment_snapshot_archives';

    protected $fillable = [
        'rotation_assignment_id',
        'payload',
        'compression',
        'checksum',
        'original_size',
    ];

    protected $casts = [
        'original_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the assignment that owns this archived snapshot.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(RotationAssignment::class, 'rotation_assignment_id');
    }

    /**
     * Restore the original snapshot after verifying its checksum.
     *
     * @return array<string, mixed>|null
     */
    public function restoreSnapshot(): ?array
    {
        if ($this->compression !== 'gzip') {
            return null;
        }

        $compressed = base64_decode($this->payload, true);
        $snapshot = $compressed === false ? false : gzdecode($compressed);

        if ($snapshot === false || ! hash_equals($this->checksum, hash('sha256', $snapshot))) {
            return null;
        }

        $decoded = json_decode($snapshot, true);

        return is_array($decoded) ? $decoded : null;
    }
}
