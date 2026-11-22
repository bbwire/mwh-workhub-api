<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkItemCommentRequest;
use App\Http\Resources\Api\V1\CommentResource;
use App\Models\Comment;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\WorkItemMentionedNotification;
use App\Policies\CommentPolicy;
use App\Services\ActivityLogger;
use App\Support\CommentMentions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkItemCommentController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(Request $request, WorkItem $workItem): AnonymousResourceCollection
    {
        $this->authorize('view', $workItem);

        $comments = Comment::query()
            ->where('work_item_id', $workItem->id)
            ->visibleToUser($request->user(), $workItem)
            ->with('user')
            ->latest()
            ->paginate($request->integer('per_page', 30));

        return CommentResource::collection($comments);
    }

    public function store(StoreWorkItemCommentRequest $request, WorkItem $workItem): JsonResponse
    {
        $this->authorize('view', $workItem);

        $visibility = $request->visibility();

        if (! app(CommentPolicy::class)->createWithVisibility($request->user(), $workItem, $visibility)) {
            abort(403);
        }

        $body = $request->string('body')->trim()->toString();
        $mentionedIds = $this->resolvedMentionIds($request, $workItem, $body);

        $comment = Comment::query()->create([
            'work_item_id' => $workItem->id,
            'user_id' => $request->user()->id,
            'body' => $body,
            'visibility' => $visibility,
        ]);

        $comment->load('user');

        $excerpt = CommentMentions::excerpt($body, 120);

        $this->activity->log($workItem, 'work_item.comment_created', $request->user(), [
            'comment_id' => $comment->id,
            'visibility' => $visibility->value,
            'mentioned_user_ids' => $mentionedIds,
            'mention_count' => count($mentionedIds),
            'excerpt' => $excerpt !== '' ? $excerpt : null,
        ]);

        $this->notifyMentionedUsers($workItem, $request->user(), $body, $mentionedIds);

        return response()->json([
            'data' => CommentResource::make($comment),
        ], 201);
    }

    /**
     * @return list<int>
     */
    private function resolvedMentionIds(StoreWorkItemCommentRequest $request, WorkItem $workItem, string $body): array
    {
        $fromBody = CommentMentions::idsFromBody($body);
        $fromRequest = collect($request->input('mentioned_user_ids', []))
            ->map(fn ($id) => (int) $id)
            ->all();

        $candidateIds = collect($fromBody)
            ->merge($fromRequest)
            ->unique()
            ->reject(fn (int $id) => $id === (int) $request->user()->id)
            ->values();

        if ($candidateIds->isEmpty()) {
            return [];
        }

        $workItem->loadMissing('workspace');

        $allowedIds = collect([
            $workItem->requester_id,
            $workItem->assignee_id,
        ]);

        if ($workItem->workspace) {
            $allowedIds = $allowedIds->merge(
                $workItem->workspace->members()->pluck('users.id')
            );
        }

        $allowed = $allowedIds->filter()->map(fn ($id) => (int) $id)->unique();

        return $candidateIds
            ->filter(fn (int $id) => $allowed->contains($id))
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $mentionedIds
     */
    private function notifyMentionedUsers(WorkItem $workItem, User $actor, string $body, array $mentionedIds): void
    {
        if ($mentionedIds === []) {
            return;
        }

        $excerpt = CommentMentions::excerpt($body);
        $users = User::query()
            ->where('is_active', true)
            ->whereIn('id', $mentionedIds)
            ->get();

        foreach ($users as $user) {
            $user->notify(new WorkItemMentionedNotification(
                $workItem,
                $actor->name,
                $excerpt,
            ));
        }
    }
}
