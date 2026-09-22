<?php

namespace Tests\Feature\Foundation;

use App\Enums\SubscriptionStatus;
use App\Models\ActivityLog;
use App\Models\AutomationRule;
use App\Models\BotFlow;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_status_reflects_expiry_date(): void
    {
        $active = User::factory()->subscribed()->create();
        $expired = User::factory()->withExpiredSubscription()->create();
        $none = User::factory()->create();

        $this->assertSame(SubscriptionStatus::Active, $active->refresh()->subscriptionStatus());
        $this->assertSame(SubscriptionStatus::Expired, $expired->refresh()->subscriptionStatus());
        $this->assertSame(SubscriptionStatus::None, $none->refresh()->subscriptionStatus());
        $this->assertTrue($active->hasActiveSubscription());
        $this->assertFalse($expired->hasActiveSubscription());
    }

    public function test_subscription_status_scope_filters_users(): void
    {
        User::factory()->subscribed()->count(2)->create();
        User::factory()->withExpiredSubscription()->create();
        User::factory()->count(3)->create();

        $this->assertSame(2, User::query()->withSubscriptionStatus(SubscriptionStatus::Active)->count());
        $this->assertSame(1, User::query()->withSubscriptionStatus(SubscriptionStatus::Expired)->count());
        $this->assertSame(3, User::query()->withSubscriptionStatus(SubscriptionStatus::None)->count());
    }

    public function test_tokens_are_stored_encrypted(): void
    {
        $user = User::factory()->create(['fb_access_token' => 'plain-user-token']);

        $this->assertNotSame('plain-user-token', $user->getRawOriginal('fb_access_token'));
        $this->assertSame('plain-user-token', $user->refresh()->fb_access_token);
    }

    public function test_domain_factories_create_related_records(): void
    {
        $rule = AutomationRule::factory()->create();
        $flow = BotFlow::factory()->create(['facebook_page_id' => $rule->facebook_page_id]);
        Conversation::factory()->create(['facebook_page_id' => $rule->facebook_page_id, 'bot_flow_id' => $flow->id]);
        ActivityLog::factory()->create(['facebook_page_id' => $rule->facebook_page_id]);

        $owner = $rule->facebookPage()->firstOrFail()->user()->firstOrFail();

        $this->assertSame(1, $owner->automationRules()->count());
        $this->assertSame(1, $owner->botFlows()->count());
        $this->assertSame(1, $owner->activityLogs()->count());
        $this->assertSame('welcome', $flow->draftDefinition()->startNode()?->id);
    }
}
