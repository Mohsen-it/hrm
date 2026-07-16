<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Companies\Models\Company;

/**
 * AttendanceGroup — a group of employees sharing the same attendance rules.
 *
 * Each group belongs to a company and can have multiple employees, schedules,
 * and an optional attendance policy.
 */
class AttendanceGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'att_attgroup';

    protected $fillable = [
        'code',
        'name',
        'company_id',
        'status',
        'create_time',
        'create_user',
        'change_time',
        'change_user',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'create_time' => 'datetime',
            'change_time' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(AttendanceEmployee::class, 'group_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(GroupSchedule::class, 'group_id');
    }

    public function policy(): HasOne
    {
        return $this->hasOne(GroupPolicy::class, 'group_id');
    }

    public function scopeByCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
