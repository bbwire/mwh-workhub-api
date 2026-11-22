<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\WorkItemMentionedNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkItemCommentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_internal_comment_hidden_from_requester(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $ictOfficer = User::query()->where('email', 'ict.officer@mswnh.test')->firstOrFail();

        $this->actingAs($staff);

        $create = $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'ict_support',
            'title' => 'VPN not connecting',
            'description' => 'Cannot access email from home.',
            'custom_fields' => ['category' => 'network'],
        ])->assertCreated();

        $workItemId = $create->json('data.id');

        $this->actingAs($ictOfficer);

        $this->postJson("/api/v1/work-items/{$workItemId}/comments", [
            'body' => 'Reset AD password on server.',
            'visibility' => 'internal',
        ])->assertCreated();

        $this->postJson("/api/v1/work-items/{$workItemId}/comments", [
            'body' => 'We are looking into this.',
            'visibility' => 'public',
        ])->assertCreated();

        $this->actingAs($staff);

        $response = $this->getJson("/api/v1/work-items/{$workItemId}/comments");
        $response->assertOk();

        $bodies = collect($response->json('data'))->pluck('body')->all();
        $this->assertContains('We are looking into this.', $bodies);
        $this->assertNotContains('Reset AD password on server.', $bodies);
    }

    public function test_team_member_sees_internal_comments(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $ictOfficer = User::query()->where('email', 'ict.officer@mswnh.test')->firstOrFail();

        $this->actingAs($staff);

        $workItemId = $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'ict_support',
            'title' => 'Printer offline',
            'description' => 'Ward printer not responding.',
            'custom_fields' => ['category' => 'hardware'],
        ])->json('data.id');

        $this->actingAs($ictOfficer);

        $this->postJson("/api/v1/work-items/{$workItemId}/comments", [
            'body' => 'Check spooler service.',
            'visibility' => 'internal',
        ])->assertCreated();

        $response = $this->getJson("/api/v1/work-items/{$workItemId}/comments");
        $response->assertOk();
        $this->assertContains('Check spooler service.', collect($response->json('data'))->pluck('body')->all());
    }

    public function test_mention_notifies_teammate(): void
    {
        Notification::fake();

        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $ictOfficer = User::query()->where('email', 'ict.officer@mswnh.test')->firstOrFail();

        $this->actingAs($staff);

        $workItemId = $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'ict_support',
            'title' => 'Laptop screen flicker',
            'description' => 'Display flickers after login.',
            'custom_fields' => ['category' => 'hardware'],
        ])->json('data.id');

        $this->actingAs($staff);

        $body = "Can you check this @[ICT Officer](user:{$ictOfficer->id})?";

        $this->postJson("/api/v1/work-items/{$workItemId}/comments", [
            'body' => $body,
            'visibility' => 'public',
            'mentioned_user_ids' => [$ictOfficer->id],
        ])->assertCreated();

        $this->getJson("/api/v1/work-items/{$workItemId}/mentionables")
            ->assertOk()
            ->assertJsonFragment(['id' => $ictOfficer->id]);

        Notification::assertSentTo($ictOfficer, WorkItemMentionedNotification::class);
    }
}
