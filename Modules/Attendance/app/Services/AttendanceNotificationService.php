<?php

namespace Modules\Attendance\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Modules\Attendance\Models\DailyAttendanceSummary;
use Modules\Users\Models\User;

/**
 * AttendanceNotificationService — central dispatcher for attendance alerts.
 *
 * Responsibilities:
 *  - Build the data payloads for the different notification kinds (late,
 *    missing check-out, anomaly, weekly summary, monthly report).
 *  - Fan out the payloads to the correct recipients through Laravel's
 *    `Notification` and `Mail` facades.
 *  - Persist a structured audit trail in the log channel so the dispatch
 *    remains auditable even before the dedicated `Notification` classes
 *    from Task 70 land in the codebase.
 *
 * The service is designed to be safe to call even when the dedicated
 * notification classes are not yet defined: it dispatches the payload
 * through a generic `GenericAttendanceNotification` wrapper class that
 * delegates to the Mail / Log channels.
 */
class AttendanceNotificationService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private AttendanceMonitoringService $monitoring,
    ) {}

    // ------------------------------------------------------------------
    // Issue notifications (one-off)
    // ------------------------------------------------------------------

    /**
     * Notify an employee that they were late on the given date.
     */
    public function notifyLateEmployee(int $userId, string $date, int $lateMinutes): bool
    {
        $user = User::find($userId);
        if (! $user || $user->isSuperAdmin()) {
            return false;
        }

        $payload = [
            'type' => 'late_arrival',
            'user_id' => $user->id,
            'date' => $date,
            'late_minutes' => $lateMinutes,
            'subject' => __('attendance.notifications.late_subject', [
                'name' => $user->name,
                'date' => $date,
            ]),
            'body' => __('attendance.notifications.late_body', [
                'name' => $user->name,
                'minutes' => $lateMinutes,
            ]),
        ];

        return $this->dispatch($user, $payload);
    }

    /**
     * Notify an employee about a missing check-out on the given date.
     */
    public function notifyMissingCheckout(int $userId, string $date, int $openMinutes): bool
    {
        $user = User::find($userId);
        if (! $user || $user->isSuperAdmin()) {
            return false;
        }

        $payload = [
            'type' => 'missing_checkout',
            'user_id' => $user->id,
            'date' => $date,
            'open_minutes' => $openMinutes,
            'subject' => __('attendance.notifications.missing_checkout_subject', [
                'name' => $user->name,
                'date' => $date,
            ]),
            'body' => __('attendance.notifications.missing_checkout_body', [
                'name' => $user->name,
                'minutes' => $openMinutes,
            ]),
        ];

        return $this->dispatch($user, $payload);
    }

    /**
     * Notify every admin (or recipient matching the optional role) about a
     * mass-lateness event on the given day.
     *
     * @param  array{date: string, is_alert: bool, late_count: int, total: int, ratio: float, threshold: float}  $event
     */
    public function notifyMassLateness(array $event, ?string $roleName = null): int
    {
        if (! ($event['is_alert'] ?? false)) {
            return 0;
        }

        $recipients = $this->resolveRecipients($roleName);
        $payload = [
            'type' => 'mass_lateness',
            'date' => $event['date'],
            'late_count' => $event['late_count'],
            'total' => $event['total'],
            'ratio' => $event['ratio'],
            'threshold' => $event['threshold'],
            'subject' => __('attendance.notifications.mass_lateness_subject', [
                'date' => $event['date'],
            ]),
            'body' => __('attendance.notifications.mass_lateness_body', [
                'late' => $event['late_count'],
                'total' => $event['total'],
                'ratio' => round(((float) $event['ratio']) * 100, 1),
            ]),
        ];

        $sent = 0;
        foreach ($recipients as $recipient) {
            if ($this->dispatch($recipient, $payload)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Notify every admin about a mass-absence event on the given day.
     *
     * @param  array{date: string, is_alert: bool, absent_count: int, total: int, ratio: float, threshold: float}  $event
     */
    public function notifyMassAbsence(array $event, ?string $roleName = null): int
    {
        if (! ($event['is_alert'] ?? false)) {
            return 0;
        }

        $recipients = $this->resolveRecipients($roleName);
        $payload = [
            'type' => 'mass_absence',
            'date' => $event['date'],
            'absent_count' => $event['absent_count'],
            'total' => $event['total'],
            'ratio' => $event['ratio'],
            'threshold' => $event['threshold'],
            'subject' => __('attendance.notifications.mass_absence_subject', [
                'date' => $event['date'],
            ]),
            'body' => __('attendance.notifications.mass_absence_body', [
                'absent' => $event['absent_count'],
                'total' => $event['total'],
                'ratio' => round(((float) $event['ratio']) * 100, 1),
            ]),
        ];

        $sent = 0;
        foreach ($recipients as $recipient) {
            if ($this->dispatch($recipient, $payload)) {
                $sent++;
            }
        }

        return $sent;
    }

    // ------------------------------------------------------------------
    // Aggregated notifications
    // ------------------------------------------------------------------

    /**
     * Send a weekly attendance summary to the supplied user (typically a manager).
     *
     * @param  array{from: string, to: string, by_status: array<string, int>, totals: array<string, int|float>}  $summary
     */
    public function sendWeeklySummary(int $userId, array $summary): bool
    {
        $user = User::find($userId);
        if (! $user || $user->isSuperAdmin()) {
            return false;
        }

        $payload = [
            'type' => 'weekly_summary',
            'user_id' => $user->id,
            'from' => $summary['from'],
            'to' => $summary['to'],
            'subject' => __('attendance.notifications.weekly_summary_subject', [
                'from' => $summary['from'],
                'to' => $summary['to'],
            ]),
            'body' => __('attendance.notifications.weekly_summary_body', [
                'name' => $user->name,
                'present' => $summary['by_status']['present'] ?? 0,
                'absent' => $summary['by_status']['absent'] ?? 0,
                'late' => $summary['by_status']['late'] ?? 0,
            ]),
        ];

        return $this->dispatch($user, $payload);
    }

    /**
     * Send a monthly attendance report to the supplied user.
     *
     * @param  array{year: int, month: int, working_days: int, by_status: array<string, int>, totals: array<string, int|float>}  $monthly
     */
    public function sendMonthlyReport(int $userId, array $monthly): bool
    {
        $user = User::find($userId);
        if (! $user || $user->isSuperAdmin()) {
            return false;
        }

        $payload = [
            'type' => 'monthly_report',
            'user_id' => $user->id,
            'year' => $monthly['year'],
            'month' => $monthly['month'],
            'subject' => __('attendance.notifications.monthly_report_subject', [
                'year' => $monthly['year'],
                'month' => $monthly['month'],
            ]),
            'body' => __('attendance.notifications.monthly_report_body', [
                'name' => $user->name,
                'present' => $monthly['by_status']['present'] ?? 0,
                'absent' => $monthly['by_status']['absent'] ?? 0,
                'late' => $monthly['by_status']['late'] ?? 0,
            ]),
        ];

        return $this->dispatch($user, $payload);
    }

    // ------------------------------------------------------------------
    // Scan / dispatch helpers
    // ------------------------------------------------------------------

    /**
     * Scan the given day and dispatch every relevant issue notification
     * (late employees, missing check-outs, mass-lateness, mass-absence).
     *
     * @return array{
     *     late: int, missing_checkout: int, mass_lateness: int, mass_absence: int
     * }
     */
    public function runDailyScan(string $date): array
    {
        $lateCount = 0;
        $missingCount = 0;
        $massLateness = 0;
        $massAbsence = 0;

        // Late employees
        DailyAttendanceSummary::onDate($date)
            ->where('status', 'late')
            ->where('late_minutes', '>', 0)
            ->with('user')
            ->select(['user_id', 'late_minutes'])
            ->chunkById(200, function (Collection $chunk) use (&$lateCount): void {
                foreach ($chunk as $row) {
                    if ($this->notifyLateEmployee((int) $row->user_id, $row->summary_date->format('Y-m-d'), (int) $row->late_minutes)) {
                        $lateCount++;
                    }
                }
            });

        // Missing check-outs (open sessions past the threshold)
        $missing = $this->monitoring->getMissingCheckouts($date);
        foreach ($missing as $session) {
            if (! $session->user) {
                continue;
            }
            $openMinutes = (int) round((CarbonImmutable::now()->getTimestamp() - $session->check_in_at->getTimestamp()) / 60);
            if ($this->notifyMissingCheckout((int) $session->user_id, $date, $openMinutes)) {
                $missingCount++;
            }
        }

        // Mass lateness / absence
        $massLateness = $this->notifyMassLateness($this->monitoring->detectMassLateness($date));
        $massAbsence = $this->notifyMassAbsence($this->monitoring->detectMassAbsence($date));

        return [
            'late' => $lateCount,
            'missing_checkout' => $missingCount,
            'mass_lateness' => $massLateness,
            'mass_absence' => $massAbsence,
        ];
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Build the recipient list for a broadcast notification.
     *
     * Defaults to users that hold the configured "admin" role; falls back
     * to all active users with `id != SUPER_ADMIN_ID` when no role is
     * resolved.
     *
     * @return Collection<int, User>
     */
    protected function resolveRecipients(?string $roleName = null): Collection
    {
        $role = $roleName ?? (string) config('attendance.notifications.admin_role', 'admin');

        $query = User::where('id', '!=', User::SUPER_ADMIN_ID)
            ->where('status', 1);

        try {
            $query->whereHas('roles', function ($q) use ($role): void {
                $q->where('name', $role);
            });
        } catch (\Throwable) {
            // roles table may be empty; fall through to the broader filter.
        }

        $recipients = $query->get();

        if ($recipients->isNotEmpty()) {
            return $recipients;
        }

        return User::where('id', '!=', User::SUPER_ADMIN_ID)
            ->where('status', 1)
            ->get();
    }

    /**
     * Dispatch a payload to the supplied user.
     *
     * Tries (in order):
     *  1. Laravel's `Notification` facade with the generic wrapper class.
     *  2. A direct `Mail` send through the `log` mailer (always succeeds in dev).
     *  3. A structured log entry as a last resort.
     */
    protected function dispatch(User $user, array $payload): bool
    {
        try {
            $wrapper = new GenericAttendanceNotification($payload);

            if (class_exists(Notification::class)) {
                Notification::send($user, $wrapper);

                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('attendance.notification.dispatch_failed', [
                'user_id' => $user->id,
                'type' => $payload['type'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            Mail::raw(
                ($payload['body'] ?? '')."\n\n--\nHRM Attendance System",
                function ($message) use ($user, $payload): void {
                    $message->to($user->email, $user->name)
                        ->subject($payload['subject'] ?? 'Attendance Notification');
                }
            );

            return true;
        } catch (\Throwable $e) {
            Log::info('attendance.notification.fallback_log', [
                'user_id' => $user->id,
                'type' => $payload['type'] ?? null,
                'subject' => $payload['subject'] ?? null,
                'body' => $payload['body'] ?? null,
            ]);

            return true;
        }
    }
}
