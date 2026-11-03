<?php

namespace App\Notifications;

use App\Models\WorkItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkItemStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly WorkItem $workItem,
        private readonly string $fromLabel,
        private readonly string $toLabel,
        private readonly string $changedByName,
    ) {}

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
            ->subject("Status update: {$reference}")
            ->greeting('Hello '.$notifiable->name)
            ->line("{$reference} moved from {$this->fromLabel} to {$this->toLabel}.")
            ->line('Updated by: '.$this->changedByName)
            ->action('View request', rtrim((string) config('app.frontend_url'), '/').'/requests/'.$this->workItem->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'work_item.status_changed',
            'work_item_id' => $this->workItem->id,
            'reference_number' => $this->workItem->reference_number,
            'title' => $this->workItem->title,
            'from_status' => $this->fromLabel,
            'to_status' => $this->toLabel,
            'changed_by' => $this->changedByName,
        ];
    }
}
