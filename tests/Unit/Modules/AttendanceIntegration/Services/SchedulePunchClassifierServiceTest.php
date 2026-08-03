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

    public function test_it_keeps_the_device_type_when_the_punch_is_outside_windows(): void
    {
        $resolver = $this->createMock(ScheduleResolverService::class);
        $resolver->method('resolve')->willReturn($this->workSchedule());

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
}
