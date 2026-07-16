<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Models\User;

/**
 * TemporarySchedule — a temporary override for an employee's time interval on a specific date.
 */
class TemporarySchedule extends Model
{
    use HasFactory;

    protected $table = 'att_temporaryschedule';

    protected $fillable = [
        'employee_id',
        'att_date',
        'time_interval_id',
        'status',
        'create_time',
        'create_user',
        'change_time',
        'change_user',
    ];

    protected function casts(): array
    {
        return [
            'att_date' => 'date',
            'status' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function timeInterval(): BelongsTo
    {
        return $this->belongsTo(TimeInterval::class, 'time_interval_id');
    }
}
