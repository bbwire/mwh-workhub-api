<?php

namespace App\Notifications;

use App\Models\WorkItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkItemAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly WorkItem $workItem,
        private readonly string $assignedByName,
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
        $title = $this->workItem->title;

        return (new MailMessage)
            ->subject("Assigned: {$reference}")
            ->greeting('Hello '.$notifiable->name)
            ->line("You have been assigned to {$reference}: {$title}.")
            ->line('Assigned by: '.$this->assignedByName)
            ->action('Open in WorkHub', rtrim((string) config('app.frontend_url'), '/').'/requests/'.$this->workItem->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'work_item.assigned',
            'work_item_id' => $this->workItem->id,
            'reference_number' => $this->workItem->reference_number,
            'title' => $this->workItem->title,
            'assigned_by' => $this->assignedByName,
        ];
    }
}
