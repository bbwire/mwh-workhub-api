<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeetingAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_management_can_upload_and_download_meeting_minutes_file(): void
    {
        $management = User::query()->where('email', 'management@mswnh.test')->firstOrFail();
        $this->actingAs($management);

        $meetingId = $this->postJson('/api/v1/meetings', [
            'title' => 'Board briefing',
            'held_at' => now()->toIso8601String(),
        ])->assertCreated()->json('data.id');

        $upload = $this->post("/api/v1/meetings/{$meetingId}/attachments", [
            'file' => UploadedFile::fake()->create('minutes.pdf', 120, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ]);

        $upload->assertCreated()
            ->assertJsonPath('data.original_name', 'minutes.pdf');

        $attachmentId = $upload->json('data.id');

        $this->getJson("/api/v1/meetings/{$meetingId}/attachments")
            ->assertOk()
            ->assertJsonPath('data.0.id', $attachmentId);

        $this->get("/api/v1/meetings/{$meetingId}/attachments/{$attachmentId}/download")
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->deleteJson("/api/v1/meetings/{$meetingId}/attachments/{$attachmentId}")
            ->assertOk();

        $this->getJson("/api/v1/meetings/{$meetingId}/attachments")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_staff_without_permission_cannot_upload_meeting_minutes(): void
    {
        $management = User::query()->where('email', 'management@mswnh.test')->firstOrFail();
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();

        $this->actingAs($management);
        $meetingId = $this->postJson('/api/v1/meetings', [
            'title' => 'Staff blocked upload',
            'held_at' => now()->toIso8601String(),
        ])->assertCreated()->json('data.id');

        $this->actingAs($staff);
        $this->post("/api/v1/meetings/{$meetingId}/attachments", [
            'file' => UploadedFile::fake()->create('minutes.pdf', 80, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ])->assertForbidden();
    }
}
