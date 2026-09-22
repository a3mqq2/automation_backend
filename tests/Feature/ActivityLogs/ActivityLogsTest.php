<?php

namespace Tests\Feature\ActivityLogs;

use App\Enums\ActivityEventType;
use App\Models\ActivityLog;
use App\Models\FacebookPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_sees_only_their_own_logs(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create(['name' => 'Shop']);
        $own = ActivityLog::factory()->create(['facebook_page_id' => $page->id]);
        $foreign = ActivityLog::factory()->create();

        $this->getJson('/api/activity-logs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.event_type', 'comment_reply')
            ->assertJsonPath('data.0.status', 'success')
            ->assertJsonPath('data.0.facebook_page.name', 'Shop')
            ->assertJsonMissingPath('data.0.client');

        $this->getJson("/api/activity-logs/{$own->id}")
            ->assertOk()
            ->assertJsonPath('data.payload.comment_id', $own->payload['comment_id']);

        $this->getJson("/api/activity-logs/{$foreign->id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'resource.not_found');
    }

    public function test_client_logs_can_be_filtered(): void
    {
        $client = $this->actingAsClient();
        $shop = FacebookPage::factory()->for($client)->create(['name' => 'Shop']);
        $cafe = FacebookPage::factory()->for($client)->create(['name' => 'Cafe']);
        ActivityLog::factory()->create(['facebook_page_id' => $shop->id]);
        ActivityLog::factory()->failed()->create(['facebook_page_id' => $shop->id]);
        ActivityLog::factory()->create(['facebook_page_id' => $cafe->id, 'event_type' => ActivityEventType::MessageReply]);

        $this->getJson("/api/activity-logs?facebook_page_id={$cafe->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/activity-logs?event_type=message_reply')->assertJsonCount(1, 'data');
        $this->getJson('/api/activity-logs?status=failed')->assertJsonCount(1, 'data');
        $this->getJson('/api/activity-logs?search=sho')->assertJsonCount(2, 'data');
        $this->getJson('/api/activity-logs?event_type=unknown')->assertJsonValidationErrors('event_type');
        $this->getJson('/api/activity-logs?status=pending')->assertJsonValidationErrors('status');
    }

    public function test_date_range_includes_the_whole_last_day(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create();
        $this->logAt($page, '2026-09-01 08:00:00');
        $this->logAt($page, '2026-09-10 23:59:30');
        $this->logAt($page, '2026-09-11 00:00:10');

        $this->getJson('/api/activity-logs?date_from=2026-09-01&date_to=2026-09-10')->assertJsonCount(2, 'data');
        $this->getJson('/api/activity-logs?date_from=2026-09-11')->assertJsonCount(1, 'data');
        $this->getJson('/api/activity-logs?date_to=2026-09-01')->assertJsonCount(1, 'data');
        $this->getJson('/api/activity-logs?date_from=2026-09-10&date_to=2026-09-01')->assertJsonValidationErrors('date_to');
    }

    public function test_client_logs_are_sorted_newest_first_and_paginated(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create();
        $older = $this->logAt($page, '2026-09-01 08:00:00');
        $newer = $this->logAt($page, '2026-09-05 08:00:00');

        $this->getJson('/api/activity-logs')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);

        $this->getJson('/api/activity-logs?sort=created_at&direction=asc&per_page=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_admin_sees_all_logs_with_client_details(): void
    {
        $this->actingAsAdmin();
        $client = User::factory()->create(['name' => 'Huda', 'email' => 'huda@example.com']);
        $log = ActivityLog::factory()->create(['facebook_page_id' => FacebookPage::factory()->for($client)->create()->id]);
        ActivityLog::factory()->count(2)->create();

        $this->getJson('/api/admin/activity-logs')->assertOk()->assertJsonCount(3, 'data');

        $this->getJson("/api/admin/activity-logs?user_id={$client->id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client.email', 'huda@example.com');

        $this->getJson("/api/admin/activity-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.client.name', 'Huda')
            ->assertJsonPath('data.facebook_page.id', $log->facebook_page_id);
    }

    public function test_access_is_separated_between_clients_and_admin(): void
    {
        $this->actingAsClient();
        $this->getJson('/api/admin/activity-logs')->assertForbidden();

        $this->actingAsAdmin();
        $this->getJson('/api/activity-logs')->assertForbidden()->assertJsonPath('code', 'auth.forbidden');
    }

    public function test_logs_require_an_active_subscription(): void
    {
        $this->actingAsClient(User::factory()->withExpiredSubscription()->create());

        $this->getJson('/api/activity-logs')->assertForbidden()->assertJsonPath('code', 'subscription.inactive');
    }

    private function logAt(FacebookPage $page, string $timestamp): ActivityLog
    {
        $log = ActivityLog::factory()->create(['facebook_page_id' => $page->id]);
        $log->forceFill(['created_at' => $timestamp])->save();

        return $log;
    }
}
