<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_login_with_email_and_password(): void
    {
        $client = User::factory()->withoutFacebook()->withPassword('secret-pass-1')->create([
            'email' => 'layla@example.com',
            'last_login_at' => null,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'LAYLA@example.com ',
            'password' => 'secret-pass-1',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'subscription']])
            ->assertJsonPath('user.id', $client->id)
            ->assertJsonPath('user.has_password', true)
            ->assertJsonPath('user.facebook_linked', false);

        $this->assertNotNull($client->refresh()->last_login_at);

        $this->withToken($response->json('token'))->getJson('/api/me')->assertOk();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->withoutFacebook()->withPassword('secret-pass-1')->create(['email' => 'layla@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'layla@example.com', 'password' => 'wrong-pass'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'auth.invalid_credentials');
    }

    public function test_unknown_email_is_rejected_with_the_same_error(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'secret-pass-1'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'auth.invalid_credentials');
    }

    public function test_facebook_only_accounts_cannot_login_with_a_password(): void
    {
        User::factory()->create(['email' => 'layla@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'layla@example.com', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'auth.invalid_credentials');
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_attempts_are_throttled(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/login', ['email' => 'layla@example.com', 'password' => 'wrong-pass'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', ['email' => 'layla@example.com', 'password' => 'wrong-pass'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'request.too_many_attempts');
    }
}
