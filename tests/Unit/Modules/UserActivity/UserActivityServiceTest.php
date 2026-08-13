<?php

namespace Tests\Unit\Modules\UserActivity;

use Illuminate\Support\Carbon;
use Modules\Settings\Services\SettingService;
use Modules\UserActivity\Repositories\UserActivityRepository;
use Modules\UserActivity\Services\UserActivityService;
use Tests\TestCase;

/**
 * Unit coverage for the active working-time algorithm.
 */
class UserActivityServiceTest extends TestCase
{
    private UserActivityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new UserActivityService(
            $this->createMock(UserActivityRepository::class),
            $this->createMock(SettingService::class),
        );
    }

    /**
     * No activity means zero minutes.
     */
    public function test_empty_timestamps_return_zero(): void
    {
        $this->assertSame(0, $this->service->calculateActiveMinutes([]));
    }

    /**
     * A single event is a point in time, not a duration.
     */
    public function test_single_timestamp_returns_zero(): void
    {
        $this->assertSame(0, $this->service->calculateActiveMinutes([now()]));
    }

    /**
     * Two events inside the idle gap form one session spanning the gap.
     */
    public function test_two_events_within_gap_are_one_session(): void
    {
        $times = [
            Carbon::parse('2026-08-12 09:00:00'),
            Carbon::parse('2026-08-12 09:25:00'),
        ];

        $this->assertSame(25, $this->service->calculateActiveMinutes($times, 30));
    }

    /**
     * A gap at or above the idle threshold splits the day into sessions;
     * gaps inside the threshold stay merged.
     */
    public function test_gap_larger_than_idle_threshold_splits_sessions(): void
    {
        $times = [
            Carbon::parse('2026-08-12 08:00:00'),
            Carbon::parse('2026-08-12 08:20:00'),
            Carbon::parse('2026-08-12 12:00:00'), // 3h40 gap → new session
            Carbon::parse('2026-08-12 12:15:00'),
        ];

        // Sessions: 8:00→8:20 (20) + 12:00→12:15 (15)
        $this->assertSame(35, $this->service->calculateActiveMinutes($times, 30));
    }

    /**
     * The default idle gap is 2 minutes: a gap of exactly two minutes is
     * not counted at all — leaving the computer for two minutes stops the
     * counter instead of inflating the working time.
     */
    public function test_gap_of_exactly_two_minutes_is_not_counted(): void
    {
        $times = [
            Carbon::parse('2026-08-12 09:00:00'),
            Carbon::parse('2026-08-12 09:02:00'),
        ];

        $this->assertSame(0, $this->service->calculateActiveMinutes($times));
    }

    /**
     * Gaps shorter than the default two-minute threshold are counted, and
     * the exact two-minute gap in between stops the counter.
     */
    public function test_gaps_under_two_minutes_are_counted(): void
    {
        $times = [
            Carbon::parse('2026-08-12 09:00:00'),
            Carbon::parse('2026-08-12 09:01:00'), // 1 min → same session
            Carbon::parse('2026-08-12 09:03:00'), // 2 min → session closes
        ];

        // Session: 9:00→9:01 (1 min); 9:03 alone contributes nothing.
        $this->assertSame(1, $this->service->calculateActiveMinutes($times));
    }

    /**
     * Durations are accumulated in seconds and rounded to the nearest
     * minute, so a 90-second span counts as 2 minutes (not 1).
     */
    public function test_durations_are_precise_to_the_second(): void
    {
        $times = [
            Carbon::parse('2026-08-12 09:00:00'),
            Carbon::parse('2026-08-12 09:01:30'),
        ];

        $this->assertSame(2, $this->service->calculateActiveMinutes($times, 30));
    }

    /**
     * Unsorted input produces the same result as sorted input.
     */
    public function test_unsorted_input_is_sorted_first(): void
    {
        $unsorted = [
            Carbon::parse('2026-08-12 09:25:00'),
            Carbon::parse('2026-08-12 09:00:00'),
            Carbon::parse('2026-08-12 09:15:00'),
        ];

        $this->assertSame(25, $this->service->calculateActiveMinutes($unsorted, 30));
    }

    /**
     * A single long session is capped at the configured maximum.
     *
     * Events every 10 minutes for 20 hours form one continuous session,
     * which must be capped at 16 hours.
     */
    public function test_session_is_capped_at_maximum(): void
    {
        $start = Carbon::parse('2026-08-12 08:00:00');

        $times = collect(range(0, 119))->map(fn (int $i): Carbon => $start->copy()->addMinutes($i * 10));

        $this->assertSame(16 * 60, $this->service->calculateActiveMinutes($times, 30));
    }

    /**
     * String timestamps are accepted as well as Carbon instances.
     */
    public function test_string_timestamps_are_accepted(): void
    {
        $times = ['2026-08-12 09:00:00', '2026-08-12 09:10:00'];

        $this->assertSame(10, $this->service->calculateActiveMinutes($times, 30));
    }
}
