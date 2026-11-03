<?php

namespace App\Notifications;

use App\Models\WorkItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkItemOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly WorkItem $workItem) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reference = $this->workItem->reference_number;

        return (new MailMessage)
            ->subject("Overdue: {$reference}")
            ->line("{$reference} is past its due date.")
            ->action('Open in WorkHub', rtrim((string) config('app.frontend_url'), '/').'/requests/'.$this->workItem->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'work_item.overdue',
            'work_item_id' => $this->workItem->id,
            'reference_number' => $this->workItem->reference_number,
            'title' => $this->workItem->title,
            'due_at' => $this->workItem->due_at?->toIso8601String(),
        ];
    }
}
