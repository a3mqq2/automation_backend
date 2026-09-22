<?php

namespace Tests\Feature\Foundation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_error_code_and_english_message(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertExactJson([
                'code' => 'auth.unauthenticated',
                'message' => 'Your session has ended. Please sign in again.',
            ])
            ->assertHeader('Content-Language', 'en');
    }

    public function test_arabic_accept_language_returns_arabic_message(): void
    {
        $this->getJson('/api/me', ['Accept-Language' => 'ar-SA,ar;q=0.9,en;q=0.8'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'auth.unauthenticated')
            ->assertJsonPath('message', 'انتهت جلستك. يرجى تسجيل الدخول مجدداً.')
            ->assertHeader('Content-Language', 'ar');
    }

    public function test_unsupported_language_falls_back_to_default_locale(): void
    {
        $this->getJson('/api/me', ['Accept-Language' => 'fr-FR'])
            ->assertUnauthorized()
            ->assertHeader('Content-Language', 'en');
    }

    public function test_unauthenticated_request_without_json_accept_header_still_returns_json(): void
    {
        $this->get('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'auth.unauthenticated');
    }

    public function test_unknown_route_returns_not_found_code(): void
    {
        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('code', 'resource.not_found');
    }
}
