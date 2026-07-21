<?php

namespace Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RotationGroup extends Model
{
    use HasFactory;

    protected $table = 'att_rotation_groups';

    protected $fillable = [
        'rotation_id',
        'name',
        'group_index',
        'time_schedule_id',
        'start_date',
    ];

    protected $casts = [
        'group_index' => 'integer',
        'start_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function rotation(): BelongsTo
    {
        return $this->belongsTo(Rotation::class, 'rotation_id');
    }

    public function timeSchedule(): BelongsTo
    {
        return $this->belongsTo(TimeSchedule::class, 'time_schedule_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RotationAssignment::class, 'rotation_group_id');
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('end_date');
    }

    public function scopeByRotation(Builder $query, int $rotationId): Builder
    {
        return $query->where('rotation_id', $rotationId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('group_index');
    }
}
