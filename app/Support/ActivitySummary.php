<?php

namespace App\Support;

use App\Models\ActivityEvent;

class ActivitySummary
{
    public static function describe(ActivityEvent $event): string
    {
        /** @var array<string, mixed> $props */
        $props = is_array($event->properties) ? $event->properties : [];

        return match ($event->event) {
            'work_item.created' => self::created($props),
            'work_item.assigned' => self::assigned($props),
            'work_item.transitioned' => self::transitioned($props),
            'work_item.comment_created' => self::commented($props),
            'work_item.attachment_uploaded' => self::attachment($props),
            default => 'Recorded an update on this task',
        };
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function created(array $props): string
    {
        $workType = self::workType($props);
        $priority = self::priority(self::string($props, 'priority'));
        $status = self::string($props, 'status_label');

        $parts = [$workType ? "Opened a new {$workType} request" : 'Opened a new task'];

        if ($priority !== null) {
            $parts[] = "marked as {$priority} priority";
        }

        if ($status !== null) {
            $parts[] = "starting in {$status}";
        }

        return count($parts) === 1
            ? $parts[0]
            : $parts[0].', '.implode(', ', array_slice($parts, 1));
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function assigned(array $props): string
    {
        $next = self::string($props, 'assignee_name');
        $previous = self::string($props, 'previous_assignee_name');
        $reason = self::string($props, 'reason');

        if ($next !== null && $previous !== null && $previous !== $next) {
            $sentence = "Reassigned the task from {$previous} to {$next}";
        } elseif ($next !== null) {
            $sentence = "Assigned the task to {$next}";
        } elseif ($previous !== null) {
            $sentence = "Removed {$previous} as the assignee";
        } else {
            $sentence = 'Cleared the assignee from this task';
        }

        if ($reason !== null) {
            $sentence .= " ({$reason})";
        }

        return $sentence;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function transitioned(array $props): string
    {
        $from = self::string($props, 'from_status_label') ?? self::statusSlug(self::string($props, 'from'));
        $to = self::string($props, 'to_status_label') ?? self::statusSlug(self::string($props, 'to'));
        $note = self::string($props, 'comment');

        if ($from !== null && $to !== null) {
            $sentence = "Moved the task from {$from} to {$to}";
        } elseif ($to !== null) {
            $sentence = "Moved the task to {$to}";
        } else {
            $sentence = 'Changed the task status';
        }

        if ($note !== null) {
            $sentence .= ". Note: {$note}";
        }

        return $sentence;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function commented(array $props): string
    {
        $visibility = self::string($props, 'visibility');
        $excerpt = self::string($props, 'excerpt');
        $mentions = isset($props['mention_count']) && is_numeric($props['mention_count'])
            ? (int) $props['mention_count']
            : (is_array($props['mentioned_user_ids'] ?? null) ? count($props['mentioned_user_ids']) : 0);

        $kind = $visibility === 'internal' ? 'an internal note' : 'a comment';
        $sentence = "Left {$kind} on the task";

        if ($mentions > 0) {
            $sentence .= $mentions === 1
                ? ' and mentioned 1 person'
                : " and mentioned {$mentions} people";
        }

        if ($excerpt !== null) {
            $sentence .= ": “{$excerpt}”";
        }

        return $sentence;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function attachment(array $props): string
    {
        $name = self::string($props, 'original_name');

        return $name !== null
            ? "Attached a file named {$name}"
            : 'Attached a file to the task';
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function workType(array $props): ?string
    {
        $named = self::string($props, 'work_type_name');
        if ($named !== null) {
            return $named;
        }

        $slug = self::string($props, 'work_type');
        if ($slug === null) {
            return null;
        }

        return str_replace(['-', '_'], ' ', $slug);
    }

    private static function priority(?string $priority): ?string
    {
        if ($priority === null) {
            return null;
        }

        return $priority === 'normal' ? 'medium' : $priority;
    }

    private static function statusSlug(?string $slug): ?string
    {
        if ($slug === null) {
            return null;
        }

        return collect(preg_split('/[-_]+/', $slug) ?: [])
            ->filter()
            ->map(fn (string $part) => ucfirst($part))
            ->implode(' ');
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function string(array $props, string $key): ?string
    {
        $value = $props[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
