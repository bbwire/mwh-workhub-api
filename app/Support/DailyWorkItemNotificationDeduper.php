<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DailyWorkItemNotificationDeduper
{
    public function alreadySent(User $user, string $type, int $workItemId): bool
    {
        if (Cache::has($this->cacheKey($user->id, $type, $workItemId))) {
            return true;
        }

        return $user->notifications()
            ->whereDate('created_at', Carbon::today())
            ->where('data->type', $type)
            ->where('data->work_item_id', $workItemId)
            ->exists();
    }

    public function markSent(User $user, string $type, int $workItemId): void
    {
        Cache::put(
            $this->cacheKey($user->id, $type, $workItemId),
            true,
            Carbon::tomorrow(),
        );
    }

    private function cacheKey(int $userId, string $type, int $workItemId): string
    {
        return 'workhub:daily-notify:'.$type.':'.$workItemId.':'.$userId.':'.Carbon::today()->toDateString();
    }
}
