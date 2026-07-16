<?php

namespace Modules\Attendance\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * GenericAttendanceNotification — wrapper used by AttendanceNotificationService.
 *
 * Holds an arbitrary payload (built by the service) and turns it into a
 * standard Laravel notification so the rest of the system can route it
 * through the user's preferred channel (mail / database / broadcast).
 *
 * Task 70 introduces type-specific notification classes; this wrapper
 * stays as the safety net for callers that fire ad-hoc notifications
 * before the dedicated classes land.
 */
class GenericAttendanceNotification extends Notification
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
     * Get the notification's delivery channels.
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
        $subject = (string) ($this->payload['subject'] ?? 'Attendance Notification');
        $body = (string) ($this->payload['body'] ?? '');

        $mail = (new MailMessage)->subject($subject);

        if ($body !== '') {
            $mail->line($body);
        }

        $mail->line(__('attendance.notifications.footer'));

        return $mail;
    }

    /**
     * Get the array representation of the notification (database / broadcast).
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return $this->payload;
    }
}
