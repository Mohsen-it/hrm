<?php

namespace Modules\Vacations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Vacations\Models\UserVacationRequest;

/**
 * VacationRequestSubmittedNotification — notify the approver that a new
 * request is waiting for a decision.
 */
class VacationRequestSubmittedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public UserVacationRequest $request,
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
        $employee = $this->request->user?->name ?? __('vacations.employee_label');

        return (new MailMessage)
            ->subject(__('vacations.notifications.submitted_subject'))
            ->greeting(__('vacations.notifications.greeting'))
            ->line(__('vacations.notifications.submitted_line_1', ['employee' => $employee]))
            ->line($this->formatRequestLine())
            ->action(
                __('vacations.notifications.review_action'),
                route('vacations.requests.show', $this->request->id)
            )
            ->line(__('vacations.notifications.footer'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => 'vacation_request_submitted',
            'request_id' => $this->request->id,
            'user_id' => $this->request->user_id,
            'vacation_type_id' => $this->request->vacation_type_id,
            'start_date' => $this->request->start_date?->format('Y-m-d'),
            'end_date' => $this->request->end_date?->format('Y-m-d'),
            'working_days' => (int) $this->request->working_days_count,
            'message' => $this->formatRequestLine(),
        ];
    }

    /**
     * Build the human-readable request summary line.
     */
    protected function formatRequestLine(): string
    {
        $type = $this->request->vacationType?->name_ar ?? __('vacations.vacation_label');

        return __('vacations.notifications.request_summary', [
            'type' => $type,
            'from' => $this->request->start_date?->format('Y-m-d'),
            'to' => $this->request->end_date?->format('Y-m-d'),
            'days' => (int) $this->request->working_days_count,
        ]);
    }
}
