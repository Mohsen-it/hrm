<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Services;

use DateTimeImmutable;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\Services\SchedulePunchClassifierService;
use Modules\Shifts\Services\ScheduleResolverService;
use Tests\TestCase;

class SchedulePunchClassifierServiceTest extends TestCase
{
    public function test_it_classifies_a_punch_in_the_exit_window_as_check_out(): void
    {
        $resolver = $this->createMock(ScheduleResolverService::class);
        $resolver->method('resolve')->willReturn($this->workSchedule());

        $service = new SchedulePunchClassifierService($resolver);

        $type = $service->classify(10, new DateTimeImmutable('2026-08-03 15:00:00'), PunchType::CheckIn);

        $this->assertSame(PunchType::CheckOut, $type);
    }

    public function test_it_returns_unknown_when_windows_are_configured_but_nothing_matches(): void
    {
        $resolver = $this->createMock(ScheduleResolverService::class);
        $resolver->method('resolve')->willReturn($this->workSchedule());

        $service = new SchedulePunchClassifierService($resolver);

        $type = $service->classify(10, new DateTimeImmutable('2026-08-03 13:00:00'), PunchType::CheckIn);

        // A device-typed check-in outside every configured window must never
        // become a phantom attendance session.
        $this->assertSame(PunchType::Unknown, $type);
    }

    public function test_it_keeps_the_device_type_when_no_windows_are_configured(): void
    {
        $resolver = $this->createMock(ScheduleResolverService::class);
        $resolver->method('resolve')->willReturn($this->windowlessSchedule());

        $service = new SchedulePunchClassifierService($resolver);

        $type = $service->classify(10, new DateTimeImmutable('2026-08-03 13:00:00'), PunchType::CheckIn);

        $this->assertSame(PunchType::CheckIn, $type);
    }

    public function test_it_returns_unknown_when_windows_overlap(): void
    {
        $resolver = $this->createMock(ScheduleResolverService::class);
        $resolver->method('resolve')->willReturn([
            ...$this->workSchedule(),
            'out_ahead_margin' => '11:00:00',
            'out_above_margin' => '15:00:00',
        ]);

        $service = new SchedulePunchClassifierService($resolver);

        $type = $service->classify(10, new DateTimeImmutable('2026-08-03 11:30:00'), PunchType::CheckIn);

        $this->assertSame(PunchType::Unknown, $type);
    }

    public function test_it_classifies_the_rest_day_morning_punch_of_an_overnight_duty_as_check_out(): void
    {
        $resolver = $this->createMock(ScheduleResolverService::class);
        $resolver->method('resolve')->willReturnCallback(
            fn ($userId, $date) => match ("{$userId}|{$date}") {
                '10|2026-08-03' => $this->overnightDutySchedule(),
                default => ['is_work_day' => false],
            }
        );

        $service = new SchedulePunchClassifierService($resolver);

        $type = $service->classify(10, new DateTimeImmutable('2026-08-04 08:20:00'), PunchType::CheckIn);

        // The departure-morning punch closes the previous duty — it must never
        // open a phantom session on the rest day.
        $this->assertSame(PunchType::CheckOut, $type);
    }

    public function test_it_classifies_a_morning_punch_of_a_consecutive_work_day_as_check_in(): void
    {
        $resolver = $this->createMock(ScheduleResolverService::class);
        $resolver->method('resolve')->willReturnCallback(
            fn ($userId, $date) => match ("{$userId}|{$date}") {
                // Two consecutive work days: the departure window of the
                // previous duty must NOT shadow the next duty's check-in.
                '10|2026-08-03', '10|2026-08-04' => $this->overnightDutySchedule(),
                default => ['is_work_day' => false],
            }
        );

        $service = new SchedulePunchClassifierService($resolver);

        $type = $service->classify(10, new DateTimeImmutable('2026-08-04 08:20:00'), PunchType::CheckIn);

        $this->assertSame(PunchType::CheckIn, $type);
    }

    /** @return array<string, mixed> */
    private function workSchedule(): array
    {
        return [
            'is_work_day' => true,
            'in_ahead_margin' => '07:00:00',
            'in_above_margin' => '12:00:00',
            'out_ahead_margin' => '14:30:00',
            'out_above_margin' => '18:00:00',
        ];
    }

    /** @return array<string, mixed> */
    private function windowlessSchedule(): array
    {
        return [
            'is_work_day' => true,
            'in_ahead_margin' => null,
            'in_above_margin' => null,
            'out_ahead_margin' => null,
            'out_above_margin' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function overnightDutySchedule(): array
    {
        return [
            'is_work_day' => true,
            'is_overnight' => true,
            'in_ahead_margin' => '04:00:00',
            'in_above_margin' => '12:00:00',
            'out_ahead_margin' => '18:00:00',
            'out_above_margin' => '23:59:00',
            'next_day_out_ahead_margin' => '07:00',
            'next_day_out_above_margin' => '10:00',
        ];
    }
}
