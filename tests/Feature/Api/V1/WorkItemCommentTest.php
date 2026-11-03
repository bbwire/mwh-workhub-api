<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
