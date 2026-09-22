<?php

namespace Tests\Feature\Auth;

use App\Models\FacebookPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_shows_subscription_and_connected_pages(): void
    {
        $client = $this->actingAsClient();
        FacebookPage::factory()->count(2)->for($client)->create();
        FacebookPage::factory()->disconnected()->for($client)->create();

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.subscription.expires_at', $client->refresh()->subscription_expires_at->toIso8601String())
            ->assertJsonPath('data.connected_pages_count', 2)
            ->assertJsonMissingPath('data.fb_access_token');
    }

    public function test_profile_is_available_without_a_subscription(): void
    {
        $this->actingAsClient(User::factory()->create());

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.subscription.status', 'none')
            ->assertJsonPath('data.subscription.expires_at', null);
    }

    public function test_profile_reports_an_expired_facebook_token(): void
    {
        $this->actingAsClient(User::factory()->withExpiredFacebookToken()->create());

        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.facebook_token_valid', false);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $client = User::factory()->create();
        $token = $client->createToken('client')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->assertSame(0, $client->tokens()->count());
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_admin_token_cannot_use_client_endpoints(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/me')->assertForbidden()->assertJsonPath('code', 'auth.forbidden');
        $this->postJson('/api/auth/logout')->assertForbidden();
    }
}
