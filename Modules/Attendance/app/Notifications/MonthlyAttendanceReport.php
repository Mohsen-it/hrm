<?php

namespace Modules\Attendance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * MonthlyAttendanceReport — rich monthly roll-up notification.
 *
 * The payload mirrors the structure produced by `MonthlyReportService`, so
 * the rendered e-mail includes the headline KPIs (present / absent / late /
 * overtime) and a small breakdown table per status.
 */
class MonthlyAttendanceReport extends Notification
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
        $year = (int) ($this->payload['year'] ?? (int) date('Y'));
        $month = (int) ($this->payload['month'] ?? (int) date('n'));

        $subject = (string) ($this->payload['subject'] ?? __('attendance.notifications.monthly_report_subject', [
            'year' => $year,
            'month' => $month,
        ]));

        $by = (array) ($this->payload['by_status'] ?? []);

        $mail = (new MailMessage)->subject($subject);

        if (isset($this->payload['user_name'])) {
            $mail->greeting(__('attendance.notifications.greeting', [
                'name' => $this->payload['user_name'],
            ]));
        }

        $mail->line(__('attendance.notifications.monthly_report_body', [
            'name' => $this->payload['user_name'] ?? '',
            'present' => (int) ($by['present'] ?? 0),
            'absent' => (int) ($by['absent'] ?? 0),
            'late' => (int) ($by['late'] ?? 0),
        ]));

        $totals = (array) ($this->payload['totals'] ?? []);
        if (! empty($totals['work_minutes']) || ! empty($totals['overtime_minutes'])) {
            $mail->line(__('attendance.notifications.monthly_report_totals', [
                'work' => (int) ($totals['work_minutes'] ?? 0),
                'overtime' => (int) ($totals['overtime_minutes'] ?? 0),
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
