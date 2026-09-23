<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\OAuthStateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class FacebookLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_returns_the_facebook_dialog_url_with_a_stored_state(): void
    {
        $url = $this->getJson('/api/auth/facebook/redirect')->assertOk()->json('data.url');

        $this->assertStringStartsWith('https://www.facebook.com/v23.0/dialog/oauth?', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('test-app-id', $query['client_id']);
        $this->assertSame('http://localhost:5173/auth/facebook/callback', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertStringContainsString('pages_messaging', $query['scope']);
        $this->assertStringContainsString('pages_manage_metadata', $query['scope']);
        $this->assertSame(40, strlen($query['state']));
        $this->assertTrue(app(OAuthStateStore::class)->consume($query['state']));
    }

    public function test_callback_creates_the_client_with_a_long_lived_encrypted_token(): void
    {
        $state = app(OAuthStateStore::class)->issue();
        $this->fakeFacebookUser();
        Http::fake([
            'graph.facebook.com/v23.0/oauth/access_token*' => Http::response([
                'access_token' => 'long-lived-token',
                'token_type' => 'bearer',
                'expires_in' => 5184000,
            ]),
        ]);

        $response = $this->getJson('/api/auth/facebook/callback?code=auth-code&state='.$state);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'subscription' => ['status', 'expires_at']]])
            ->assertJsonPath('user.fb_user_id', '10150000000001')
            ->assertJsonPath('user.name', 'Layla Hassan')
            ->assertJsonPath('user.subscription.status', 'none')
            ->assertJsonPath('user.facebook_token_valid', true)
            ->assertJsonMissingPath('user.fb_access_token');

        $client = User::query()->where('fb_user_id', '10150000000001')->firstOrFail();

        $this->assertSame('long-lived-token', $client->fb_access_token);
        $this->assertNotSame('long-lived-token', $client->getRawOriginal('fb_access_token'));
        $this->assertSame('https://cdn.example.com/layla.jpg', $client->avatar_url);
        $this->assertTrue($client->token_expires_at->between(now()->addDays(59), now()->addDays(61)));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'grant_type=fb_exchange_token')
            && str_contains($request->url(), 'fb_exchange_token=short-lived-token'));

        $this->withToken($response->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $client->id);
    }

    public function test_second_login_updates_the_existing_client(): void
    {
        $client = User::factory()->create(['fb_user_id' => '10150000000001', 'name' => 'Old Name']);
        Http::fake(['graph.facebook.com/*' => Http::response(['access_token' => 'long-lived-token', 'expires_in' => 5184000])]);
        $this->fakeFacebookUser();

        $this->getJson('/api/auth/facebook/callback?code=auth-code&state='.app(OAuthStateStore::class)->issue())->assertOk();

        $this->assertSame(1, User::query()->count());
        $this->assertSame('Layla Hassan', $client->refresh()->name);
        $this->assertSame('long-lived-token', $client->fb_access_token);
    }

    public function test_facebook_login_into_a_password_account_keeps_its_name_and_email(): void
    {
        $client = User::factory()->withPassword()->create([
            'fb_user_id' => '10150000000001',
            'name' => 'Chosen Name',
            'email' => 'chosen@example.com',
            'avatar_url' => null,
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['access_token' => 'long-lived-token', 'expires_in' => 5184000])]);
        $this->fakeFacebookUser();

        $this->getJson('/api/auth/facebook/callback?code=auth-code&state='.app(OAuthStateStore::class)->issue())
            ->assertOk()
            ->assertJsonPath('user.name', 'Chosen Name')
            ->assertJsonPath('user.email', 'chosen@example.com')
            ->assertJsonPath('user.has_password', true);

        $client->refresh();

        $this->assertSame('long-lived-token', $client->fb_access_token);
        $this->assertSame('https://cdn.example.com/layla.jpg', $client->avatar_url);
    }

    public function test_facebook_login_does_not_claim_an_email_used_by_another_account(): void
    {
        User::factory()->withoutFacebook()->withPassword()->create(['email' => 'layla@example.com']);
        Http::fake(['graph.facebook.com/*' => Http::response(['access_token' => 'long-lived-token', 'expires_in' => 5184000])]);
        $this->fakeFacebookUser();

        $this->getJson('/api/auth/facebook/callback?code=auth-code&state='.app(OAuthStateStore::class)->issue())
            ->assertOk()
            ->assertJsonPath('user.fb_user_id', '10150000000001')
            ->assertJsonPath('user.email', null)
            ->assertJsonPath('user.has_password', false);

        $this->assertSame(2, User::query()->count());
    }

    public function test_token_exchange_failure_falls_back_to_the_short_lived_token(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid', 'code' => 100]], 400)]);
        $this->fakeFacebookUser();

        $this->getJson('/api/auth/facebook/callback?code=auth-code&state='.app(OAuthStateStore::class)->issue())->assertOk();

        $client = User::query()->firstOrFail();

        $this->assertSame('short-lived-token', $client->fb_access_token);
        $this->assertTrue($client->token_expires_at->between(now()->addMinutes(55), now()->addMinutes(65)));
    }

    public function test_callback_rejects_unknown_state(): void
    {
        $this->fakeFacebookUser();

        $this->getJson('/api/auth/facebook/callback?code=auth-code&state=forged-state')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'auth.invalid_state');

        $this->assertSame(0, User::query()->count());
    }

    public function test_state_cannot_be_reused(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['access_token' => 'long-lived-token', 'expires_in' => 5184000])]);
        $this->fakeFacebookUser();
        $state = app(OAuthStateStore::class)->issue();

        $this->getJson('/api/auth/facebook/callback?code=auth-code&state='.$state)->assertOk();
        $this->getJson('/api/auth/facebook/callback?code=auth-code&state='.$state)
            ->assertJsonPath('code', 'auth.invalid_state');
    }

    public function test_callback_requires_code_and_state(): void
    {
        $this->getJson('/api/auth/facebook/callback')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'state']);
    }

    public function test_denied_permission_returns_facebook_failed(): void
    {
        $this->getJson('/api/auth/facebook/callback?error=access_denied&state='.app(OAuthStateStore::class)->issue())
            ->assertUnprocessable()
            ->assertJsonPath('code', 'auth.facebook_failed');
    }

    public function test_socialite_failure_returns_facebook_failed(): void
    {
        Socialite::fake('facebook', fn () => throw new \RuntimeException('Invalid verification code'));

        $this->getJson('/api/auth/facebook/callback?code=bad-code&state='.app(OAuthStateStore::class)->issue())
            ->assertUnprocessable()
            ->assertJsonPath('code', 'auth.facebook_failed');
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
