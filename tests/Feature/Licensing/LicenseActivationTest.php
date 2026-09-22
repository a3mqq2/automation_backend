<?php

namespace Tests\Feature\Licensing;

use App\Models\LicenseKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_without_subscription_can_activate_a_key(): void
    {
        $client = $this->actingAsClient(User::factory()->create());
        $licenseKey = LicenseKey::factory()->create(['key' => 'ABCDE-FGHJK-LMNPQ-RSTUV', 'expires_at' => now()->addMonths(6)]);

        $this->postJson('/api/auth/license-key/activate', ['key' => 'ABCDE-FGHJK-LMNPQ-RSTUV'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.expires_at', $licenseKey->expires_at->toIso8601String())
            ->assertJsonPath('data.license_key', '*****-*****-*****-RSTUV');

        $client->refresh();
        $licenseKey->refresh();

        $this->assertTrue($client->hasActiveSubscription());
        $this->assertSame($licenseKey->id, $client->active_license_key_id);
        $this->assertTrue($licenseKey->is_used);
        $this->assertSame($client->id, $licenseKey->used_by);
        $this->assertNotNull($licenseKey->used_at);
    }

    public function test_key_input_is_normalized(): void
    {
        $this->actingAsClient(User::factory()->create());
        LicenseKey::factory()->create(['key' => 'ABCDE-FGHJK-LMNPQ-RSTUV']);

        $this->postJson('/api/auth/license-key/activate', ['key' => ' abcde fghjk lmnpq rstuv '])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_unknown_key_is_rejected(): void
    {
        $this->actingAsClient(User::factory()->create());

        $this->postJson('/api/auth/license-key/activate', ['key' => 'ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'license_key.invalid');
    }

    public function test_used_key_cannot_be_activated_by_another_client(): void
    {
        $owner = User::factory()->create();
        $licenseKey = LicenseKey::factory()->usedBy($owner)->create();
        $this->actingAsClient(User::factory()->create());

        $this->postJson('/api/auth/license-key/activate', ['key' => $licenseKey->key])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'license_key.already_used');

        $this->assertSame($owner->id, $licenseKey->refresh()->used_by);
    }

    public function test_key_cannot_be_activated_twice_by_the_same_client(): void
    {
        $this->actingAsClient(User::factory()->create());
        $licenseKey = LicenseKey::factory()->create();

        $this->postJson('/api/auth/license-key/activate', ['key' => $licenseKey->key])->assertOk();
        $this->postJson('/api/auth/license-key/activate', ['key' => $licenseKey->key])
            ->assertJsonPath('code', 'license_key.already_used');
    }

    public function test_expired_key_is_rejected(): void
    {
        $this->actingAsClient(User::factory()->create());
        $licenseKey = LicenseKey::factory()->expired()->create();

        $this->postJson('/api/auth/license-key/activate', ['key' => $licenseKey->key])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'license_key.expired');
    }

    public function test_key_must_extend_an_active_subscription(): void
    {
        $client = $this->actingAsClient(User::factory()->subscribed(now()->addMonths(6))->create());
        $shorter = LicenseKey::factory()->create(['expires_at' => now()->addMonth()]);
        $longer = LicenseKey::factory()->create(['expires_at' => now()->addYear()]);

        $this->postJson('/api/auth/license-key/activate', ['key' => $shorter->key])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'license_key.does_not_extend');
        $this->assertFalse($shorter->refresh()->is_used);

        $this->postJson('/api/auth/license-key/activate', ['key' => $longer->key])->assertOk();
        $this->assertSame($longer->id, $client->refresh()->active_license_key_id);
    }

    public function test_client_with_expired_subscription_can_renew(): void
    {
        $client = $this->actingAsClient(User::factory()->withExpiredSubscription()->create());
        $licenseKey = LicenseKey::factory()->create();

        $this->postJson('/api/auth/license-key/activate', ['key' => $licenseKey->key])->assertOk();

        $this->assertTrue($client->refresh()->hasActiveSubscription());
    }

    public function test_key_is_required(): void
    {
        $this->actingAsClient(User::factory()->create());

        $this->postJson('/api/auth/license-key/activate', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');
    }

    public function test_activation_is_throttled(): void
    {
        $this->actingAsClient(User::factory()->create());

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/license-key/activate', ['key' => 'ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ'])->assertUnprocessable();
        }

        $this->postJson('/api/auth/license-key/activate', ['key' => 'ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ'])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'request.too_many_attempts');
    }

    public function test_admin_token_cannot_activate_keys(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/auth/license-key/activate', ['key' => 'ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ'])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');
    }

    public function test_guest_cannot_activate_keys(): void
    {
        $this->postJson('/api/auth/license-key/activate', ['key' => 'ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ'])->assertUnauthorized();
    }
}
