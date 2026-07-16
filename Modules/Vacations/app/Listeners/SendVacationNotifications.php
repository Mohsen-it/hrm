<?php

namespace Modules\Vacations\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Modules\Users\Models\User;
use Modules\Vacations\Events\VacationApproved;
use Modules\Vacations\Events\VacationCancelled;
use Modules\Vacations\Events\VacationRejected;
use Modules\Vacations\Events\VacationRequested;
use Modules\Vacations\Notifications\VacationApprovedNotification;
use Modules\Vacations\Notifications\VacationRejectedNotification;
use Modules\Vacations\Notifications\VacationRequestSubmittedNotification;

/**
 * SendVacationNotifications — fan-out notifications for every vacation event.
 *
 * The actual delivery (mail / database / broadcast) is delegated to
 * dedicated Notification classes so the listener stays free of payload
 * formatting concerns. Failures are swallowed into the log because
 * notifying is best-effort; the database is the source of truth.
 */
class SendVacationNotifications implements ShouldQueue
{
    /**
     * Queue connection / tube for the listener.
     */
    public string $queue = 'notifications';

    /**
     * Number of seconds the listener may run before timing out.
     */
    public int $timeout = 60;

    /**
     * Handle the `VacationRequested` event.
     */
    public function handleRequested(VacationRequested $event): void
    {
        try {
            $recipients = $this->resolveRecipients($event->request, ['manager']);
            foreach ($recipients as $user) {
                $user->notify(new VacationRequestSubmittedNotification($event->request));
            }
        } catch (\Throwable $e) {
            Log::warning('VacationRequested notification failed', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the `VacationApproved` event.
     */
    public function handleApproved(VacationApproved $event): void
    {
        try {
            $recipients = $this->resolveRecipients($event->request, ['employee']);
            foreach ($recipients as $user) {
                $user->notify(new VacationApprovedNotification($event->request));
            }
        } catch (\Throwable $e) {
            Log::warning('VacationApproved notification failed', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the `VacationRejected` event.
     */
    public function handleRejected(VacationRejected $event): void
    {
        try {
            $recipients = $this->resolveRecipients($event->request, ['employee']);
            foreach ($recipients as $user) {
                $user->notify(new VacationRejectedNotification($event->request));
            }
        } catch (\Throwable $e) {
            Log::warning('VacationRejected notification failed', [
                'request_id' => $event->request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the `VacationCancelled` event (no-op for now; reserved for
     * future "manager notified on cancellation" flow).
     */
    public function handleCancelled(VacationCancelled $event): void
    {
        // Reserved for future use.
    }

    /**
     * Resolve the users that should receive a notification for the supplied
     * request, given the audience tags.
     *
     * @param  array<int, string>  $tags  Allowed: employee | manager
     * @return iterable<User>
     */
    protected function resolveRecipients($request, array $tags): iterable
    {
        $recipients = [];

        if (in_array('employee', $tags, true) && $request->user) {
            $recipients[] = $request->user;
        }

        if (in_array('manager', $tags, true) && $request->manager) {
            $recipients[] = $request->manager;
        }

        return $recipients;
    }
}
