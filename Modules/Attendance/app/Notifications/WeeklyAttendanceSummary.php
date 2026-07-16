<?php

namespace Modules\Attendance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * WeeklyAttendanceSummary — compact 7-day attendance recap.
 *
 * The payload mirrors the structure produced by `AttendanceReportService`
 * for a one-week range so the rendered e-mail highlights the totals and the
 * day-by-day status breakdown.
 */
class WeeklyAttendanceSummary extends Notification
{
    use Queueable;

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
        $from = (string) ($this->payload['from'] ?? '');
        $to = (string) ($this->payload['to'] ?? '');

        $subject = (string) ($this->payload['subject'] ?? __('attendance.notifications.weekly_summary_subject', [
            'from' => $from,
            'to' => $to,
        ]));

        $by = (array) ($this->payload['by_status'] ?? []);

        $mail = (new MailMessage)->subject($subject);

        if (isset($this->payload['user_name'])) {
            $mail->greeting(__('attendance.notifications.greeting', [
                'name' => $this->payload['user_name'],
            ]));
        }

        $mail->line(__('attendance.notifications.weekly_summary_body', [
            'name' => $this->payload['user_name'] ?? '',
            'present' => (int) ($by['present'] ?? 0),
            'absent' => (int) ($by['absent'] ?? 0),
            'late' => (int) ($by['late'] ?? 0),
        ]));

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
