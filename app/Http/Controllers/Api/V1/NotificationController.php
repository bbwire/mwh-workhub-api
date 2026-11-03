<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return NotificationResource::collection($notifications);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        /** @var DatabaseNotification|null $record */
        $record = $request->user()
            ->notifications()
            ->where('id', $notification)
            ->first();

        if ($record === null) {
            abort(404);
        }

        if ($record->read_at === null) {
            $record->markAsRead();
        }

        return response()->json([
            'data' => NotificationResource::make($record->fresh()),
        ]);
    }
}
