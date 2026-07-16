<?php

namespace Modules\AttendanceIntegration\Listeners;

use Modules\AttendanceIntegration\Events\PunchReceived;
use Modules\AttendanceIntegration\Services\LivePunchFeedService;

class PublishLivePunchEvent
{
    public function __construct(
        private LivePunchFeedService $feedService,
    ) {}

    public function handle(PunchReceived $event): void
    {
        $this->feedService->addPunch([
            'device' => $event->device?->toArray() ?? ['id' => 0, 'name' => 'Unknown'],
            'user' => [
                'id' => $event->user->id,
                'name' => $event->user->name,
                'employee_code' => $event->user->employee_code,
            ],
            'punch_type' => $event->punch->punchType->value,
            'punched_at' => $event->punch->timestamp->format(DATE_ATOM),
            'session_id' => $event->session->id,
            'status' => $event->session->status,
        ]);
    }
}
