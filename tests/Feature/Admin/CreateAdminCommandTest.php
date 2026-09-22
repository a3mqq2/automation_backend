<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin(): void
    {
        $this->artisan('admin:create', ['email' => 'Owner@Example.com', '--name' => 'Owner'])
            ->expectsQuestion('Password', 'long-enough-password')
            ->expectsQuestion('Confirm password', 'long-enough-password')
            ->expectsOutput('Admin owner@example.com created.')
            ->assertSuccessful();

        $admin = Admin::query()->where('email', 'owner@example.com')->firstOrFail();

        $this->assertSame('Owner', $admin->name);
        $this->assertTrue(Hash::check('long-enough-password', $admin->password));
    }

    public function test_it_refuses_an_existing_email_without_force(): void
    {
        Admin::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('admin:create', ['email' => 'owner@example.com', '--name' => 'Owner'])
            ->assertFailed();

        $this->assertSame(1, Admin::query()->count());
    }

    public function test_force_resets_the_password(): void
    {
        Admin::factory()->create(['email' => 'owner@example.com', 'password' => 'old-password']);

        $this->artisan('admin:create', ['email' => 'owner@example.com', '--name' => 'Owner', '--force' => true])
            ->expectsQuestion('Password', 'brand-new-password')
            ->expectsQuestion('Confirm password', 'brand-new-password')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('brand-new-password', Admin::query()->firstOrFail()->password));
    }

    public function test_it_rejects_short_or_mismatched_passwords(): void
    {
        $this->artisan('admin:create', ['email' => 'owner@example.com', '--name' => 'Owner'])
            ->expectsQuestion('Password', 'short')
            ->assertFailed();

        $this->artisan('admin:create', ['email' => 'owner@example.com', '--name' => 'Owner'])
            ->expectsQuestion('Password', 'long-enough-password')
            ->expectsQuestion('Confirm password', 'different-password')
            ->assertFailed();

        $this->assertSame(0, Admin::query()->count());
    }

    public function test_it_rejects_an_invalid_email(): void
    {
        $this->artisan('admin:create', ['email' => 'not-an-email', '--name' => 'Owner'])->assertFailed();

        $this->assertSame(0, Admin::query()->count());
    }
}
