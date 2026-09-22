<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_log_in_and_use_the_token(): void
    {
        $admin = Admin::factory()->create(['email' => 'owner@example.com', 'password' => 'secret-password']);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'OWNER@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'admin' => ['id', 'name', 'email', 'last_login_at']])
            ->assertJsonPath('admin.id', $admin->id)
            ->assertJsonMissingPath('admin.password');

        $this->assertNotNull($admin->refresh()->last_login_at);

        $this->withToken($response->json('token'))
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'owner@example.com');
    }

    public function test_wrong_password_is_rejected(): void
    {
        Admin::factory()->create(['email' => 'owner@example.com', 'password' => 'secret-password']);

        $this->postJson('/api/admin/login', ['email' => 'owner@example.com', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'auth.invalid_credentials')
            ->assertJsonMissingPath('token');
    }

    public function test_unknown_email_is_rejected_with_the_same_error(): void
    {
        $this->postJson('/api/admin/login', ['email' => 'nobody@example.com', 'password' => 'whatever-password'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'auth.invalid_credentials');
    }

    public function test_login_validates_input(): void
    {
        $this->postJson('/api/admin/login', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation.failed')
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $payload = ['email' => 'owner@example.com', 'password' => 'wrong-password'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/admin/login', $payload)->assertUnprocessable();
        }

        $this->postJson('/api/admin/login', $payload)
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'request.too_many_attempts')
            ->assertHeader('Retry-After');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        Admin::factory()->create(['email' => 'owner@example.com', 'password' => 'secret-password']);
        $token = $this->postJson('/api/admin/login', ['email' => 'owner@example.com', 'password' => 'secret-password'])->json('token');

        $this->withToken($token)->postJson('/api/admin/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/admin/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'auth.unauthenticated');
    }

    public function test_client_token_cannot_access_admin_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->subscribed()->create());

        $this->getJson('/api/admin/me')->assertForbidden()->assertJsonPath('code', 'auth.forbidden');
        $this->getJson('/api/admin/clients')->assertForbidden();
        $this->getJson('/api/admin/stats')->assertForbidden();
    }

    public function test_guest_cannot_access_admin_endpoints(): void
    {
        $this->getJson('/api/admin/stats')->assertUnauthorized()->assertJsonPath('code', 'auth.unauthenticated');
    }
}
