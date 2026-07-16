<?php

namespace Modules\Vacations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * GenericVacationNotification — wrapper used by the Vacations services
 * to fire ad-hoc notifications (operator-driven adjustments, mass
 * year-end rolls, ...) before the dedicated lifecycle notifications
 * are available.
 */
class GenericVacationNotification extends Notification
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
        $channels = (array) config('vacations.notifications.channels', ['mail', 'database']);

        return array_values(array_filter($channels, fn ($c) => is_string($c) && $c !== ''));
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $subject = (string) ($this->payload['subject'] ?? 'Vacation Notification');
        $body = (string) ($this->payload['body'] ?? '');

        $mail = (new MailMessage)->subject($subject);
        if ($body !== '') {
            $mail->line($body);
        }
        $mail->line(__('vacations.notifications.footer'));

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
