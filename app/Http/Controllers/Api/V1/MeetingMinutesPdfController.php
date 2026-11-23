<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MeetingMinutesPdfController extends Controller
{
    public function __invoke(Request $request, Meeting $meeting): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        if (! $user->can('meetings.manage')) {
            $canSeeLinkedWork = $meeting->workItems()
                ->visibleTo($user)
                ->exists();
            abort_unless($canSeeLinkedWork, 403);
        }

        $body = filled($meeting->minutes) ? $meeting->minutes : $meeting->notes;
        abort_unless(filled($body), 422, 'No written minutes to download.');

        $meeting->loadMissing('creator');

        $html = view('reports.meeting-minutes-pdf', [
            'meeting' => $meeting,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf('meeting-%d-minutes-%s.pdf', $meeting->id, now()->format('Y-m-d'));

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
