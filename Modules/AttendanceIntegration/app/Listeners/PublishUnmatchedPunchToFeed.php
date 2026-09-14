<?php

namespace Modules\AttendanceIntegration\Listeners;

use Modules\AttendanceIntegration\Events\UnmatchedPunchReceived;
use Modules\AttendanceIntegration\Services\LivePunchFeedService;

class PublishUnmatchedPunchToFeed
{
    public function __construct(
        private LivePunchFeedService $feedService,
    ) {}

    public function handle(UnmatchedPunchReceived $event): void
    {
        $this->feedService->addPunch([
            'device' => $event->device?->toArray() ?? ['id' => 0, 'name' => 'Unknown'],
            'user' => [
                'id' => $event->user->id,
                'name' => $event->user->name,
                'employee_code' => $event->user->employee_code,
                // O(1) string concat on the already-loaded model — no extra query.
                'avatar_url' => $event->user->avatar_url,
            ],
            'punch_type' => ($event->classifiedPunchType ?? $event->punch->punchType)->value,
            'verify_method' => $event->punch->verifyMethod->value,
            'punched_at' => $event->punch->timestamp->format(DATE_ATOM),
            'session_id' => null,
            'status' => 'unmatched',
        ]);
    }
}
