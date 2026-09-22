<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityEventType;
use App\Models\ActivityLog;
use App\Models\FacebookPage;
use App\Models\LicenseKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_summarize_the_platform(): void
    {
        $this->actingAsAdmin();

        $subscribed = User::factory()->subscribed()->create();
        User::factory()->withExpiredSubscription()->create();
        User::factory()->count(2)->create();

        $page = FacebookPage::factory()->for($subscribed)->create();
        FacebookPage::factory()->disconnected()->for($subscribed)->create();

        ActivityLog::factory()->count(3)->create(['facebook_page_id' => $page->id]);
        ActivityLog::factory()->create(['facebook_page_id' => $page->id, 'event_type' => ActivityEventType::MessageReply]);
        ActivityLog::factory()->failed()->create(['facebook_page_id' => $page->id]);
        $old = ActivityLog::factory()->create(['facebook_page_id' => $page->id]);
        $old->forceFill(['created_at' => now()->subDays(45)])->save();

        LicenseKey::factory()->count(2)->create();
        LicenseKey::factory()->expired()->create();

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertExactJson(['data' => [
                'clients' => [
                    'total' => 4,
                    'active_subscriptions' => 1,
                    'expired_subscriptions' => 1,
                    'without_subscription' => 2,
                ],
                'pages' => [
                    'connected' => 1,
                    'total' => 2,
                ],
                'automation' => [
                    'replies_total' => 5,
                    'replies_last_30_days' => 4,
                    'failed_total' => 1,
                ],
                'license_keys' => [
                    'issued' => 5,
                    'used' => 2,
                    'remaining' => 2,
                    'expired_unused' => 1,
                ],
            ]]);
    }
}
