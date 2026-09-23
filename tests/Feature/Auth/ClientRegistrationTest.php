<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_register_with_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => '  Layla Hassan ',
            'email' => 'Layla@Example.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'subscription' => ['status', 'expires_at']]])
            ->assertJsonPath('user.name', 'Layla Hassan')
            ->assertJsonPath('user.email', 'layla@example.com')
            ->assertJsonPath('user.has_password', true)
            ->assertJsonPath('user.facebook_linked', false)
            ->assertJsonPath('user.fb_user_id', null)
            ->assertJsonPath('user.facebook_token_valid', false)
            ->assertJsonPath('user.subscription.status', 'none')
            ->assertJsonMissingPath('user.password');

        $client = User::query()->where('email', 'layla@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('secret-pass-1', $client->password));
        $this->assertNull($client->fb_user_id);
        $this->assertNotNull($client->last_login_at);

        $this->withToken($response->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $client->id);
    }

    public function test_registration_rejects_an_email_that_is_already_used(): void
    {
        User::factory()->create(['email' => 'layla@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Layla',
            'email' => 'LAYLA@example.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation.failed')
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(1, User::query()->count());
    }

    public function test_registration_validates_the_password(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Layla',
            'email' => 'layla@example.com',
            'password' => 'short',
            'password_confirmation' => 'other',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertSame(0, User::query()->count());
    }

    public function test_registration_validation_messages_are_arabic(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'ليلى',
            'email' => 'layla@example.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'different',
        ], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'تأكيد حقل كلمة المرور غير متطابق.');
    }

    public function test_registration_does_not_require_a_subscription_to_read_the_profile(): void
    {
        $token = $this->postJson('/api/auth/register', [
            'name' => 'Layla',
            'email' => 'layla@example.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ])->json('token');

        $this->withToken($token)->getJson('/api/pages')
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription.inactive');
    }
}
