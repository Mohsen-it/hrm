<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AttCode — defines attendance status codes (present, absent, late, etc.).
 */
class AttCode extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'att_attcode';

    protected $fillable = [
        'code',
        'alias',
        'display_format',
        'symbol',
        'round_off',
        'min_val',
        'symbol_only',
        'order',
        'color_setting',
    ];

    protected function casts(): array
    {
        return [
            'symbol_only' => 'boolean',
        ];
    }
}
