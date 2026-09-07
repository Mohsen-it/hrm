<?php

namespace Tests\Unit\Modules\Shifts;

use Modules\Shifts\Listeners\SyncVacationToShiftException;
use Modules\Shifts\Services\ShiftExceptionService;
use Modules\Vacations\Events\VacationApproved;
use Modules\Vacations\Events\VacationCancelled;
use Modules\Vacations\Models\UserVacationRequest;
use Tests\TestCase;

class SyncVacationToShiftExceptionTest extends TestCase
{
    public function test_handle_routes_approved_event_without_throwing(): void
    {
        $service = $this->createMock(ShiftExceptionService::class);
        $service->expects($this->once())->method('mirrorVacation');

        $request = $this->makeRequest();
        (new SyncVacationToShiftException($service))->handle(new VacationApproved($request));
    }

    public function test_handle_routes_cancelled_event_without_throwing(): void
    {
        $service = $this->createMock(ShiftExceptionService::class);
        $service->expects($this->once())->method('unmirrorVacation')->with(7);

        $request = $this->makeRequest();
        (new SyncVacationToShiftException($service))->handle(new VacationCancelled($request));
    }

    private function makeRequest(): UserVacationRequest
    {
        // Unsaved real model: attribute access (incl. date casts) behaves
        // like production, unlike a mock (which returns null for magic props).
        $request = new UserVacationRequest([
            'user_id' => 3,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
        ]);
        $request->id = 7;

        return $request;
    }
}
