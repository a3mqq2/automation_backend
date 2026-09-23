<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\FacebookLinkStateStore;
use App\Services\Auth\OAuthStateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class FacebookLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlinked_client_cannot_list_pages(): void
    {
        $this->actingAsClient(User::factory()->withoutFacebook()->withPassword()->subscribed()->create());
        Http::fake();

        $this->getJson('/api/pages')
            ->assertForbidden()
            ->assertJsonPath('code', 'facebook.not_linked');

        Http::assertNothingSent();
    }

    public function test_link_redirect_returns_the_facebook_dialog_url_with_a_state_bound_to_the_client(): void
    {
        $client = $this->actingAsClient(User::factory()->withoutFacebook()->withPassword()->subscribed()->create());

        $url = $this->getJson('/api/auth/facebook/link')->assertOk()->json('data.url');

        $this->assertStringStartsWith('https://www.facebook.com/v23.0/dialog/oauth?', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('http://localhost:5173/auth/facebook/callback', $query['redirect_uri']);
        $this->assertSame(40, strlen($query['state']));
        $this->assertFalse(app(FacebookLinkStateStore::class)->consume($query['state'], User::factory()->create()));
        $this->assertFalse(app(FacebookLinkStateStore::class)->consume($query['state'], $client));
    }

    public function test_link_requires_an_active_subscription(): void
    {
        $this->actingAsClient(User::factory()->withoutFacebook()->withPassword()->create());

        $this->getJson('/api/auth/facebook/link')
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription.inactive');
    }

    public function test_completing_the_link_stores_the_facebook_account_without_touching_the_profile(): void
    {
        $client = $this->actingAsClient(User::factory()->withoutFacebook()->withPassword()->subscribed()->create([
            'name' => 'Layla Hassan',
            'email' => 'layla@example.com',
        ]));
        $this->fakeFacebookUser();
        Http::fake([
            'graph.facebook.com/v23.0/oauth/access_token*' => Http::response([
                'access_token' => 'long-lived-token',
                'token_type' => 'bearer',
                'expires_in' => 5184000,
            ]),
        ]);

        $response = $this->postJson('/api/auth/facebook/link', [
            'code' => 'auth-code',
            'state' => app(FacebookLinkStateStore::class)->issue($client),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.name', 'Layla Hassan')
            ->assertJsonPath('data.email', 'layla@example.com')
            ->assertJsonPath('data.fb_user_id', '10150000000001')
            ->assertJsonPath('data.facebook_linked', true)
            ->assertJsonPath('data.facebook_token_valid', true)
            ->assertJsonPath('data.has_password', true)
            ->assertJsonMissingPath('data.fb_access_token');

        $client->refresh();

        $this->assertSame('10150000000001', $client->fb_user_id);
        $this->assertSame('long-lived-token', $client->fb_access_token);
        $this->assertSame('https://cdn.example.com/layla.jpg', $client->avatar_url);
        $this->assertTrue($client->token_expires_at->between(now()->addDays(59), now()->addDays(61)));
        $this->assertSame(1, User::query()->count());
    }

    public function test_linked_client_can_list_pages(): void
    {
        $client = $this->actingAsClient(User::factory()->withoutFacebook()->withPassword()->subscribed()->create());
        $this->fakeFacebookUser();
        Http::fake([
            'graph.facebook.com/v23.0/oauth/access_token*' => Http::response(['access_token' => 'long-lived-token', 'expires_in' => 5184000]),
            'graph.facebook.com/v23.0/me/accounts*' => Http::response(['data' => [
                ['id' => '111111111111', 'name' => 'Shop', 'access_token' => 'shop-page-token', 'tasks' => ['MODERATE', 'MESSAGING']],
            ]]),
        ]);

        $this->postJson('/api/auth/facebook/link', [
            'code' => 'auth-code',
            'state' => app(FacebookLinkStateStore::class)->issue($client),
        ])->assertOk();

        $this->getJson('/api/pages')->assertOk()->assertJsonPath('data.0.page_id', '111111111111');
    }

    public function test_relinking_the_same_facebook_account_refreshes_the_token(): void
    {
        $client = $this->actingAsClient(User::factory()->subscribed()->withExpiredFacebookToken()->create(['fb_user_id' => '10150000000001']));
        $this->fakeFacebookUser();
        Http::fake(['graph.facebook.com/*' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 5184000])]);

        $this->postJson('/api/auth/facebook/link', [
            'code' => 'auth-code',
            'state' => app(FacebookLinkStateStore::class)->issue($client),
        ])->assertOk()->assertJsonPath('data.facebook_token_valid', true);

        $this->assertSame('fresh-token', $client->refresh()->fb_access_token);
    }

    public function test_facebook_account_linked_to_another_client_is_rejected(): void
    {
        User::factory()->create(['fb_user_id' => '10150000000001']);
        $client = $this->actingAsClient(User::factory()->withoutFacebook()->withPassword()->subscribed()->create());
        $this->fakeFacebookUser();
        Http::fake(['graph.facebook.com/*' => Http::response(['access_token' => 'long-lived-token', 'expires_in' => 5184000])]);

        $this->postJson('/api/auth/facebook/link', [
            'code' => 'auth-code',
            'state' => app(FacebookLinkStateStore::class)->issue($client),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'facebook.account_already_linked');

        $this->assertNull($client->refresh()->fb_user_id);
    }

    public function test_link_rejects_a_state_issued_for_another_client(): void
    {
        $otherClient = User::factory()->create();
        $client = $this->actingAsClient(User::factory()->withoutFacebook()->withPassword()->subscribed()->create());
        $this->fakeFacebookUser();

        $this->postJson('/api/auth/facebook/link', [
            'code' => 'auth-code',
            'state' => app(FacebookLinkStateStore::class)->issue($otherClient),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'auth.invalid_state');

        $this->assertNull($client->refresh()->fb_user_id);
    }

    public function test_login_state_cannot_be_used_to_link(): void
    {
        $client = $this->actingAsClient(User::factory()->withoutFacebook()->withPassword()->subscribed()->create());
        $this->fakeFacebookUser();

        $this->postJson('/api/auth/facebook/link', ['code' => 'auth-code', 'state' => app(OAuthStateStore::class)->issue()])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'auth.invalid_state');

        $this->assertNull($client->refresh()->fb_user_id);
    }

    public function test_denied_permission_returns_link_failed(): void
    {
        $client = $this->actingAsClient(User::factory()->withoutFacebook()->withPassword()->subscribed()->create());

        $this->postJson('/api/auth/facebook/link', [
            'error' => 'access_denied',
            'state' => app(FacebookLinkStateStore::class)->issue($client),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'facebook.link_failed');
    }

    public function test_socialite_failure_returns_link_failed(): void
    {
        $client = $this->actingAsClient(User::factory()->withoutFacebook()->withPassword()->subscribed()->create());
        Socialite::fake('facebook', fn () => throw new \RuntimeException('Invalid verification code'));

        $this->postJson('/api/auth/facebook/link', [
            'code' => 'bad-code',
            'state' => app(FacebookLinkStateStore::class)->issue($client),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'facebook.link_failed');
    }

    public function test_link_requires_authentication(): void
    {
        $this->getJson('/api/auth/facebook/link')->assertUnauthorized();
        $this->postJson('/api/auth/facebook/link', ['code' => 'x', 'state' => 'y'])->assertUnauthorized();
    }

    public function test_admin_token_cannot_use_the_link_endpoint(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/auth/facebook/link')->assertForbidden()->assertJsonPath('code', 'auth.forbidden');
    }

    private function fakeFacebookUser(): void
    {
        Socialite::fake('facebook', SocialiteUser::fake([
            'id' => '10150000000001',
            'name' => 'Layla Hassan',
            'email' => 'layla@example.com',
            'avatar' => 'https://graph.facebook.com/v23.0/10150000000001/picture?type=normal',
            'token' => 'short-lived-token',
            'expiresIn' => 3600,
            'picture' => ['data' => ['url' => 'https://cdn.example.com/layla.jpg']],
        ]));
    }
}
