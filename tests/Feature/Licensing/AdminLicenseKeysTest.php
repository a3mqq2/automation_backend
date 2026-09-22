<?php

namespace Tests\Feature\Licensing;

use App\Models\LicenseKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLicenseKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_issue_a_license_key(): void
    {
        $admin = $this->actingAsAdmin();
        $expiresAt = now()->addMonths(3)->startOfSecond();

        $response = $this->postJson('/api/admin/license-keys', [
            'expires_at' => $expiresAt->toIso8601String(),
            'note' => '  Quarterly plan  ',
        ]);

        $response->assertCreated()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'available')
            ->assertJsonPath('data.0.is_used', false)
            ->assertJsonPath('data.0.note', 'Quarterly plan')
            ->assertJsonPath('data.0.created_by.id', $admin->id)
            ->assertJsonPath('data.0.used_by', null);

        $licenseKey = LicenseKey::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}(-[A-Z0-9]{5}){3}$/', $licenseKey->key);
        $this->assertTrue($licenseKey->expires_at->equalTo($expiresAt));
        $this->assertSame($admin->id, $licenseKey->created_by);
    }

    public function test_admin_can_issue_keys_in_bulk(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/license-keys', [
            'expires_at' => now()->addYear()->toIso8601String(),
            'quantity' => 25,
        ])->assertCreated()->assertJsonCount(25, 'data');

        $this->assertSame(25, LicenseKey::query()->distinct()->count('key'));
    }

    public function test_issuing_validates_expiry_and_quantity(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/license-keys', ['expires_at' => now()->subDay()->toIso8601String()])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation.failed')
            ->assertJsonValidationErrors('expires_at');

        $this->postJson('/api/admin/license-keys', [])
            ->assertJsonValidationErrors('expires_at');

        $this->postJson('/api/admin/license-keys', ['expires_at' => now()->addDay()->toIso8601String(), 'quantity' => 101])
            ->assertJsonValidationErrors('quantity');

        $this->assertSame(0, LicenseKey::query()->count());
    }

    public function test_list_shows_usage_details_and_supports_status_filters(): void
    {
        $this->actingAsAdmin();
        $client = User::factory()->create(['name' => 'Mona', 'email' => 'mona@example.com']);
        $used = LicenseKey::factory()->usedBy($client)->create();
        $available = LicenseKey::factory()->create();
        $expired = LicenseKey::factory()->expired()->create();

        $this->getJson('/api/admin/license-keys')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/admin/license-keys?status=used')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $used->id)
            ->assertJsonPath('data.0.status', 'used')
            ->assertJsonPath('data.0.used_by.email', 'mona@example.com');

        $this->getJson('/api/admin/license-keys?status=available')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $available->id);

        $this->getJson('/api/admin/license-keys?status=expired')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $expired->id)
            ->assertJsonPath('data.0.is_expired', true);

        $this->getJson('/api/admin/license-keys?status=bogus')->assertJsonValidationErrors('status');
    }

    public function test_list_can_be_searched_by_key_note_or_client(): void
    {
        $this->actingAsAdmin();
        $client = User::factory()->create(['name' => 'Mona', 'email' => 'mona@example.com']);
        $used = LicenseKey::factory()->usedBy($client)->create();
        $noted = LicenseKey::factory()->create(['note' => 'Ramadan promo']);
        $plain = LicenseKey::factory()->create();

        $this->getJson('/api/admin/license-keys?search=mona')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $used->id);
        $this->getJson('/api/admin/license-keys?search=ramadan')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $noted->id);
        $this->getJson('/api/admin/license-keys?search='.substr($plain->key, 6, 5))->assertJsonPath('data.0.id', $plain->id);
    }

    public function test_list_can_be_sorted_by_expiry(): void
    {
        $this->actingAsAdmin();
        $later = LicenseKey::factory()->create(['expires_at' => now()->addYear()]);
        $sooner = LicenseKey::factory()->create(['expires_at' => now()->addWeek()]);

        $this->getJson('/api/admin/license-keys?sort=expires_at&direction=asc')
            ->assertJsonPath('data.0.id', $sooner->id)
            ->assertJsonPath('data.1.id', $later->id);
    }

    public function test_admin_can_view_a_key(): void
    {
        $this->actingAsAdmin();
        $licenseKey = LicenseKey::factory()->create();

        $this->getJson("/api/admin/license-keys/{$licenseKey->id}")
            ->assertOk()
            ->assertJsonPath('data.key', $licenseKey->key);

        $this->getJson('/api/admin/license-keys/9999')->assertNotFound()->assertJsonPath('code', 'resource.not_found');
    }

    public function test_unused_keys_can_be_deleted_but_used_keys_cannot(): void
    {
        $this->actingAsAdmin();
        $unused = LicenseKey::factory()->create();
        $used = LicenseKey::factory()->usedBy(User::factory()->create())->create();

        $this->deleteJson("/api/admin/license-keys/{$unused->id}")->assertNoContent();
        $this->assertModelMissing($unused);

        $this->deleteJson("/api/admin/license-keys/{$used->id}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'license_key.used_cannot_be_deleted');
        $this->assertModelExists($used);
    }

    public function test_clients_cannot_manage_license_keys(): void
    {
        $this->actingAsClient();

        $this->getJson('/api/admin/license-keys')->assertForbidden()->assertJsonPath('code', 'auth.forbidden');
        $this->postJson('/api/admin/license-keys', ['expires_at' => now()->addDay()->toIso8601String()])->assertForbidden();
    }
}
