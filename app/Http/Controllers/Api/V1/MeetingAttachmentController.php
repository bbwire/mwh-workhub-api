<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMeetingAttachmentRequest;
use App\Http\Resources\Api\V1\MeetingAttachmentResource;
use App\Models\Meeting;
use App\Models\MeetingAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeetingAttachmentController extends Controller
{
    public function index(Request $request, Meeting $meeting): AnonymousResourceCollection
    {
        $this->authorizeMeetingAccess($request, $meeting);

        $attachments = MeetingAttachment::query()
            ->where('meeting_id', $meeting->id)
            ->with('uploadedBy')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return MeetingAttachmentResource::collection($attachments);
    }

    public function store(StoreMeetingAttachmentRequest $request, Meeting $meeting): JsonResponse
    {
        $file = $request->file('file');
        $disk = 'local';
        $directory = 'meetings/'.$meeting->id;
        $path = $file->store($directory, $disk);

        $attachment = MeetingAttachment::query()->create([
            'meeting_id' => $meeting->id,
            'uploaded_by_id' => $request->user()->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
        ]);

        $attachment->load('uploadedBy');

        return response()->json([
            'data' => MeetingAttachmentResource::make($attachment),
        ], 201);
    }

    public function download(Request $request, Meeting $meeting, MeetingAttachment $attachment): StreamedResponse
    {
        $this->authorizeMeetingAccess($request, $meeting);

        if ($attachment->meeting_id !== $meeting->id) {
            abort(404);
        }

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        $disk = Storage::disk($attachment->disk);

        if ($request->boolean('inline')) {
            return $disk->response(
                $attachment->path,
                $attachment->original_name,
                [
                    'Content-Type' => $attachment->mime_type,
                    'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
                ],
            );
        }

        return $disk->download(
            $attachment->path,
            $attachment->original_name,
        );
    }

    public function destroy(Request $request, Meeting $meeting, MeetingAttachment $attachment): JsonResponse
    {
        abort_unless($request->user()?->can('meetings.manage'), 403);

        if ($attachment->meeting_id !== $meeting->id) {
            abort(404);
        }

        $attachment->deleteFile();
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted.']);
    }

    private function authorizeMeetingAccess(Request $request, Meeting $meeting): void
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        if ($user->can('meetings.manage')) {
            return;
        }

        $canSeeLinkedWork = $meeting->workItems()
            ->visibleTo($user)
            ->exists();
        abort_unless($canSeeLinkedWork, 403);
    }
}
