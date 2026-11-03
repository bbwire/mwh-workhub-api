<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkItemAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_user_can_upload_and_download_attachment(): void
    {
        $user = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($user);

        $workItemId = $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'maintenance_request',
            'title' => 'Broken window',
            'description' => 'Glass cracked in office.',
            'custom_fields' => ['location' => 'Block B'],
        ])->json('data.id');

        $upload = $this->post("/api/v1/work-items/{$workItemId}/attachments", [
            'file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $upload->assertCreated()
            ->assertJsonPath('data.original_name', 'photo.jpg');

        $attachmentId = $upload->json('data.id');

        $this->getJson("/api/v1/work-items/{$workItemId}/attachments")
            ->assertOk()
            ->assertJsonPath('data.0.id', $attachmentId);

        $this->get("/api/v1/work-items/{$workItemId}/attachments/{$attachmentId}/download")
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
