<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkItemAttachmentRequest;
use App\Http\Resources\Api\V1\AttachmentResource;
use App\Models\Attachment;
use App\Models\WorkItem;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkItemAttachmentController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(Request $request, WorkItem $workItem): AnonymousResourceCollection
    {
        $this->authorize('view', $workItem);

        $attachments = Attachment::query()
            ->where('work_item_id', $workItem->id)
            ->with('uploadedBy')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return AttachmentResource::collection($attachments);
    }

    public function store(StoreWorkItemAttachmentRequest $request, WorkItem $workItem): JsonResponse
    {
        $this->authorize('view', $workItem);

        $file = $request->file('file');
        $disk = 'local';
        $directory = 'work-items/'.$workItem->id;
        $path = $file->store($directory, $disk);

        $attachment = Attachment::query()->create([
            'work_item_id' => $workItem->id,
            'uploaded_by_id' => $request->user()->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
        ]);

        $attachment->load('uploadedBy');

        $this->activity->log($workItem, 'work_item.attachment_uploaded', $request->user(), [
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
        ]);

        return response()->json([
            'data' => AttachmentResource::make($attachment),
        ], 201);
    }

    public function download(Request $request, WorkItem $workItem, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $workItem);

        if ($attachment->work_item_id !== $workItem->id) {
            abort(404);
        }

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
        );
    }
}
