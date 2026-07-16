<?php

namespace Modules\Holidays\Services;

use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Modules\Holidays\Models\Holiday;
use Modules\Holidays\Repositories\HolidayRepository;

/**
 * HolidayService — CRUD orchestration for the holiday calendar.
 *
 * Owns payload validation (recurring vs fixed), exposes the public read
 * helpers (active list, materialised occurrences inside a date range) and
 * maintains a compact, in-memory cache of upcoming dates so the
 * attendance integration layer can read it synchronously.
 */
class HolidayService
{
    /**
     * Memoised list of upcoming holiday dates keyed by `Y-m-d`.
     *
     * The cache is intentionally tiny: a single year's worth of dates is
     * a few dozen strings, so the cost of re-materialising is trivial
     * and the data lives only inside the request lifecycle.
     *
     * @var array<string, true>|null
     */
    protected ?array $cachedDates = null;

    /**
     * Create a new service instance.
     */
    public function __construct(
        private HolidayRepository $repository,
    ) {}

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Get a paginated list of holidays filtered by the supplied bag.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAllHolidays(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->getAll($filters, $perPage);
    }

    /**
     * Find a holiday by its primary key.
     */
    public function findHoliday(int $id): ?Holiday
    {
        return $this->repository->findById($id);
    }

    /**
     * Return every active holiday whose materialised dates intersect
     * the supplied range.
     *
     * @return Collection<int, Holiday>
     */
    public function getActiveInRange(string $from, string $to): Collection
    {
        return $this->repository->getActiveInRange($from, $to);
    }

    /**
     * Get a `Y-m-d => name_ar` map of every holiday date that falls in
     * the supplied range. The list is memoised for the lifetime of the
     * request so repeated lookups stay O(1).
     *
     * @return array<string, string>
     */
    public function dateMapForRange(string $from, string $to): array
    {
        $map = [];
        foreach ($this->repository->getActiveInRange($from, $to) as $holiday) {
            /** @var Holiday $holiday */
            foreach ($holiday->occurrencesInRange($from, $to) as $date) {
                $map[$date] = $holiday->name_ar;
            }
        }
        ksort($map);

        return $map;
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    /**
     * Create a new holiday.
     *
     * @param  array<string, mixed>  $data
     */
    public function createHoliday(array $data): Holiday
    {
        $payload = $this->validatePayload($data, null);

        return $this->repository->create($payload);
    }

    /**
     * Update an existing holiday.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateHoliday(Holiday $holiday, array $data): Holiday
    {
        $payload = $this->validatePayload($data, $holiday);

        return $this->repository->update($holiday, $payload);
    }

    /**
     * Soft delete a holiday.
     */
    public function deleteHoliday(Holiday $holiday): bool
    {
        $this->forgetCache();

        return $this->repository->delete($holiday);
    }

    // ------------------------------------------------------------------
    // Cache helpers
    // ------------------------------------------------------------------

    /**
     * Read the memoised map of upcoming holiday dates.
     *
     * The window defaults to `HOLIDAYS_LOOKBACK_DAYS` past and
     * `HOLIDAYS_LOOKAHEAD_DAYS` ahead of today.
     *
     * @return array<string, true>
     */
    public function upcomingDateSet(?DateTimeImmutable $anchor = null): array
    {
        if ($this->cachedDates !== null) {
            return $this->cachedDates;
        }

        $anchor = $anchor ?? new DateTimeImmutable('today');
        $lookback = (int) config('holidays.lookback_days', 7);
        $lookahead = (int) config('holidays.lookahead_days', 365);

        $from = $anchor->modify("-{$lookback} days")->format('Y-m-d');
        $to = $anchor->modify("+{$lookahead} days")->format('Y-m-d');

        $set = [];
        foreach ($this->repository->getActiveInRange($from, $to) as $holiday) {
            /** @var Holiday $holiday */
            foreach ($holiday->occurrencesInRange($from, $to) as $date) {
                $set[$date] = true;
            }
        }

        return $this->cachedDates = $set;
    }

    /**
     * Determine whether the supplied date is a holiday according to the
     * memoised cache.
     */
    public function isHoliday(string $date): bool
    {
        return array_key_exists($date, $this->upcomingDateSet());
    }

    /**
     * Drop the memoised date cache (called by writers).
     */
    public function forgetCache(): void
    {
        $this->cachedDates = null;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Validate the supplied payload, normalising recurring semantics.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException When the payload is invalid.
     */
    protected function validatePayload(array $data, ?Holiday $ignore): array
    {
        if (empty($data['name_ar'])) {
            throw new InvalidArgumentException(
                __('holidays.name_required')
            );
        }

        $isRecurring = (bool) ($data['is_recurring'] ?? false);
        $date = $data['date'] ?? null;
        $month = $data['recurring_month'] ?? null;
        $day = $data['recurring_day'] ?? null;

        if (! $isRecurring) {
            if (! $date) {
                throw new InvalidArgumentException(
                    __('holidays.date_required_when_not_recurring')
                );
            }
        } else {
            $month = (int) ($month ?? 0);
            $day = (int) ($day ?? 0);
            if ($month < 1 || $month > 12) {
                throw new InvalidArgumentException(
                    __('holidays.recurring_month_invalid')
                );
            }
            if ($day < 1 || $day > 31) {
                throw new InvalidArgumentException(
                    __('holidays.recurring_day_invalid')
                );
            }
        }

        $duration = (int) ($data['duration_days'] ?? 1);
        if ($duration < 1) {
            throw new InvalidArgumentException(
                __('holidays.duration_must_be_positive')
            );
        }

        return [
            'name_ar' => (string) $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'code' => $data['code'] ?? null,
            'is_recurring' => $isRecurring,
            'date' => $isRecurring ? null : $date,
            'recurring_month' => $isRecurring ? $month : null,
            'recurring_day' => $isRecurring ? $day : null,
            'category' => (string) ($data['category'] ?? 'public'),
            'is_paid' => (bool) ($data['is_paid'] ?? true),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'duration_days' => $duration,
            'applies_to_all' => (bool) ($data['applies_to_all'] ?? true),
            'applies_to_branches' => $data['applies_to_branches'] ?? null,
            'applies_to_departments' => $data['applies_to_departments'] ?? null,
            'description' => $data['description'] ?? null,
        ];
    }
}
