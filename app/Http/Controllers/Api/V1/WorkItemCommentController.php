<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkItemCommentRequest;
use App\Http\Resources\Api\V1\CommentResource;
use App\Models\Comment;
use App\Models\WorkItem;
use App\Policies\CommentPolicy;
use App\Services\ActivityLogger;
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

        $comment = Comment::query()->create([
            'work_item_id' => $workItem->id,
            'user_id' => $request->user()->id,
            'body' => $request->string('body')->trim(),
            'visibility' => $visibility,
        ]);

        $comment->load('user');

        $this->activity->log($workItem, 'work_item.comment_created', $request->user(), [
            'comment_id' => $comment->id,
            'visibility' => $visibility->value,
        ]);

        return response()->json([
            'data' => CommentResource::make($comment),
        ], 201);
    }
}
