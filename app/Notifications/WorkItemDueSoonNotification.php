<?php

namespace App\Notifications;

use App\Models\WorkItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkItemDueSoonNotification extends Notification implements ShouldQueue
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
            ->subject("Due soon: {$reference}")
            ->line("{$reference} is due on ".$this->workItem->due_at?->toDayDateTimeString().'.')
            ->action('Open in WorkHub', rtrim((string) config('app.frontend_url'), '/').'/requests/'.$this->workItem->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'work_item.due_soon',
            'work_item_id' => $this->workItem->id,
            'reference_number' => $this->workItem->reference_number,
            'title' => $this->workItem->title,
            'due_at' => $this->workItem->due_at?->toIso8601String(),
        ];
    }
}
