<?php

namespace Modules\Holidays\Models;

use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Holiday — a single company / public holiday definition.
 *
 * Two flavours are supported:
 *  - **Fixed**: `date` is set, `is_recurring` is false.
 *  - **Yearly**: `recurring_month` and `recurring_day` are set,
 *    `is_recurring` is true. The {@see self::occurrencesInRange()} helper
 *    materialises the actual dates inside a range.
 */
class Holiday extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'holidays';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name_ar', 'name_en', 'code',
        'is_recurring', 'date', 'recurring_month', 'recurring_day',
        'category', 'is_paid', 'is_active',
        'duration_days', 'applies_to_all',
        'applies_to_branches', 'applies_to_departments',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_recurring' => 'boolean',
            'date' => 'date',
            'recurring_month' => 'integer',
            'recurring_day' => 'integer',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
            'duration_days' => 'integer',
            'applies_to_all' => 'boolean',
            'applies_to_branches' => 'array',
            'applies_to_departments' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    /**
     * Scope a query to active holidays only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to holidays that fall inside the supplied range.
     *
     * The match is on either a fixed `date` falling inside the range, or
     * a recurring (month, day) tuple whose materialised dates fall in the
     * range.
     */
    public function scopeInRange(Builder $query, string $from, string $to): Builder
    {
        return $query->where(function (Builder $q) use ($from, $to): void {
            $q->where(function (Builder $fixed) use ($from, $to): void {
                $fixed->where('is_recurring', false)
                    ->whereBetween('date', [$from, $to]);
            })->orWhere(function (Builder $recur) use ($from, $to): void {
                $recur->where('is_recurring', true)
                    ->where(function (Builder $sub) use ($from, $to): void {
                        $fromTs = strtotime($from);
                        $toTs = strtotime($to);
                        for ($ts = $fromTs; $ts <= $toTs; $ts = strtotime('+1 day', $ts)) {
                            $m = (int) date('n', $ts);
                            $d = (int) date('j', $ts);
                            $sub->orWhere(function (Builder $s) use ($m, $d): void {
                                $s->where('recurring_month', $m)
                                    ->where('recurring_day', $d);
                            });
                        }
                    });
            });
        });
    }

    // ------------------------------------------------------------------
    // Computed helpers
    // ------------------------------------------------------------------

    /**
     * Materialise every concrete date this holiday occupies in the
     * supplied range, considering `duration_days` and overnight roll-overs.
     *
     * @return array<int, string> Y-m-d strings
     */
    public function occurrencesInRange(string $from, string $to): array
    {
        if (! $this->is_active) {
            return [];
        }

        $duration = max(1, (int) $this->duration_days);
        $dates = [];

        if (! $this->is_recurring && $this->date) {
            $anchor = $this->date->format('Y-m-d');
            if ($anchor >= $from && $anchor <= $to) {
                $dates[] = $anchor;
                for ($i = 1; $i < $duration; $i++) {
                    $next = date('Y-m-d', strtotime("+{$i} day", strtotime($anchor)));
                    if ($next > $to) {
                        break;
                    }
                    $dates[] = $next;
                }
            }
        }

        if ($this->is_recurring && $this->recurring_month && $this->recurring_day) {
            foreach (CarbonPeriod::create($from, $to) as $day) {
                if ((int) $day->format('n') === (int) $this->recurring_month
                    && (int) $day->format('j') === (int) $this->recurring_day) {
                    $dates[] = $day->format('Y-m-d');
                    for ($i = 1; $i < $duration; $i++) {
                        $next = $day->copy()->addDays($i)->format('Y-m-d');
                        if ($next > $to) {
                            break;
                        }
                        $dates[] = $next;
                    }
                }
            }
        }

        return array_values(array_unique($dates));
    }

    /**
     * Localised display name with English fallback to Arabic.
     */
    public function displayName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'en' && $this->name_en) {
            return $this->name_en;
        }

        return $this->name_ar;
    }
}
