<?php

namespace Tests;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.facebook.client_id' => 'test-app-id',
            'services.facebook.client_secret' => 'test-app-secret',
            'services.facebook.redirect' => 'http://localhost:5173/auth/facebook/callback',
            'meta.graph_base_url' => 'https://graph.facebook.com',
            'meta.graph_version' => 'v23.0',
            'meta.webhook_verify_token' => 'test-verify-token',
        ]);
    }

    protected function actingAsClient(?User $user = null): User
    {
        $user ??= User::factory()->subscribed()->create();

        Sanctum::actingAs($user);

        return $user;
    }

    protected function actingAsAdmin(?Admin $admin = null): Admin
    {
        $admin ??= Admin::factory()->create();

        Sanctum::actingAs($admin);

        return $admin;
    }
}
