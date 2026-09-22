<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\AutomationRule;
use App\Models\BotFlow;
use App\Models\FacebookPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_list_shows_subscription_and_connected_pages(): void
    {
        $this->actingAsAdmin();
        $client = User::factory()->subscribed()->create(['name' => 'Sara']);
        FacebookPage::factory()->count(2)->for($client)->create();
        FacebookPage::factory()->disconnected()->for($client)->create();

        $response = $this->getJson('/api/admin/clients')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $client->id)
            ->assertJsonPath('data.0.subscription_status', 'active')
            ->assertJsonPath('data.0.connected_pages_count', 2)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.0.fb_access_token');
    }

    public function test_clients_can_be_filtered_by_subscription_status(): void
    {
        $this->actingAsAdmin();
        User::factory()->subscribed()->count(2)->create();
        $expired = User::factory()->withExpiredSubscription()->create();
        User::factory()->create();

        $this->getJson('/api/admin/clients?subscription_status=expired')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $expired->id)
            ->assertJsonPath('data.0.subscription_status', 'expired');

        $this->getJson('/api/admin/clients?subscription_status=active')->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/clients?subscription_status=none')->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/clients?subscription_status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subscription_status');
    }

    public function test_clients_can_be_searched_sorted_and_paginated(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['name' => 'Charlie', 'email' => 'charlie@example.com']);
        User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        User::factory()->create(['name' => 'Bob', 'email' => 'bob@shop.test']);

        $this->getJson('/api/admin/clients?search=shop.test')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bob');

        $this->getJson('/api/admin/clients?sort=name&direction=asc')
            ->assertJsonPath('data.0.name', 'Alice')
            ->assertJsonPath('data.2.name', 'Charlie');

        $this->getJson('/api/admin/clients?sort=name&direction=asc&per_page=2&page=2')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Charlie')
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/admin/clients?sort=fb_access_token')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    public function test_clients_can_be_sorted_by_connected_pages_count(): void
    {
        $this->actingAsAdmin();
        $busy = User::factory()->create();
        FacebookPage::factory()->count(3)->for($busy)->create();
        User::factory()->create();

        $this->getJson('/api/admin/clients?sort=connected_pages_count&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $busy->id)
            ->assertJsonPath('data.0.connected_pages_count', 3);
    }

    public function test_client_detail_includes_pages_keys_and_counts(): void
    {
        $this->actingAsAdmin();
        $client = User::factory()->subscribed()->create();
        $page = FacebookPage::factory()->for($client)->create();
        AutomationRule::factory()->count(2)->create(['facebook_page_id' => $page->id]);
        BotFlow::factory()->create(['facebook_page_id' => $page->id]);
        ActivityLog::factory()->count(4)->create(['facebook_page_id' => $page->id]);

        $response = $this->getJson("/api/admin/clients/{$client->id}")->assertOk();

        $response->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.fb_user_id', $client->fb_user_id)
            ->assertJsonPath('data.active_license_key.id', $client->refresh()->active_license_key_id)
            ->assertJsonCount(1, 'data.pages')
            ->assertJsonPath('data.pages.0.page_id', $page->page_id)
            ->assertJsonCount(1, 'data.license_keys_history')
            ->assertJsonPath('data.automation_rules_count', 2)
            ->assertJsonPath('data.bot_flows_count', 1)
            ->assertJsonPath('data.activity_logs_count', 4)
            ->assertJsonPath('data.connected_pages_count', 1)
            ->assertJsonMissingPath('data.fb_access_token')
            ->assertJsonMissingPath('data.pages.0.page_access_token');
    }

    public function test_client_detail_returns_not_found_for_unknown_client(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/clients/999')
            ->assertNotFound()
            ->assertJsonPath('code', 'resource.not_found');
    }
}
