<?php

namespace Modules\Attendance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * AttendanceIssue — single-issue notification (late / missing check-out /
 * mass-event). Used by the operator-driven flows that bypass the generic
 * wrapper (e.g. a console command that escalates a single anomaly).
 *
 * The notification carries its `issue_type` in the payload so the rendered
 * subject / body lines up with the right translation key.
 */
class AttendanceIssue extends Notification
{
    use Queueable;

    /**
     * Recognised issue types — the payload's `issue_type` is matched against
     * this list to pick the right translation key.
     */
    public const ISSUE_LATE = 'late';

    public const ISSUE_MISSING_CHECKOUT = 'missing_checkout';

    public const ISSUE_MASS_LATENESS = 'mass_lateness';

    public const ISSUE_MASS_ABSENCE = 'mass_absence';

    /**
     * Create a new notification instance.
     *
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload = [],
    ) {}

    /**
     * Get the delivery channels for the notification.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = (array) config('attendance.notifications.channels', ['mail', 'database']);

        return array_values(array_filter($channels, fn ($c) => is_string($c) && $c !== ''));
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $issue = (string) ($this->payload['issue_type'] ?? self::ISSUE_LATE);
        $subject = (string) ($this->payload['subject'] ?? __('attendance.notifications.issue_subject', [
            'type' => $issue,
        ]));

        $mail = (new MailMessage)->subject($subject);

        $body = (string) ($this->payload['body'] ?? '');
        if ($body !== '') {
            $mail->line($body);
        }

        if (! empty($this->payload['date'])) {
            $mail->line(__('attendance.notifications.issue_date', [
                'date' => $this->payload['date'],
            ]));
        }

        $mail->line(__('attendance.notifications.footer'));

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return $this->payload;
    }
}
