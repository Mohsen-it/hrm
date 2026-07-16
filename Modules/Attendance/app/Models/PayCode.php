<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PayCode — defines payroll codes for work, leave, overtime, etc.
 */
class PayCode extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'att_paycode';

    protected $fillable = [
        'code',
        'name',
        'code_type',
        'tag',
        'fixed_code',
        'is_work',
        'fixed_hours',
        'is_paid',
        'is_benefit',
        'round_off',
        'min_val',
        'display_format',
        'symbol',
        'display_order',
        'desc',
        'is_display',
        'is_default',
        'color_setting',
        'create_time',
        'create_user',
        'change_time',
        'change_user',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'code_type' => 'integer',
            'is_work' => 'boolean',
            'fixed_hours' => 'decimal:2',
            'is_paid' => 'boolean',
            'is_benefit' => 'boolean',
            'is_display' => 'boolean',
            'is_default' => 'boolean',
            'status' => 'integer',
        ];
    }

    public function scopeByType(Builder $query, int $type): Builder
    {
        return $query->where('code_type', $type);
    }

    public function scopeWork(Builder $query): Builder
    {
        return $query->where('is_work', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
