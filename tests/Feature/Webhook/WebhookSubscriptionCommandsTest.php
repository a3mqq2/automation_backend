<?php

namespace Tests\Feature\Webhook;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookSubscriptionCommandsTest extends TestCase
{
    public function test_subscribe_registers_the_callback_url_from_app_url(): void
    {
        config(['app.url' => 'https://example.test/']);
        $this->fakeGraph();

        $this->artisan('webhook:subscribe')->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_contains($request->url(), '/v23.0/test-app-id/subscriptions')
            && str_contains($request->url(), 'access_token=test-app-id%7Ctest-app-secret')
            && $request['object'] === 'page'
            && $request['callback_url'] === 'https://example.test/api/webhook'
            && $request['verify_token'] === 'test-verify-token'
            && $request['fields'] === 'feed,messages,messaging_postbacks'
            && $request['include_values'] === true);
    }

    public function test_subscribe_accepts_a_url_override(): void
    {
        $this->fakeGraph();

        $this->artisan('webhook:subscribe', ['--url' => 'https://tunnel.test/api/webhook'])->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['callback_url'] === 'https://tunnel.test/api/webhook');
    }

    public function test_subscribe_reports_a_meta_rejection(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'The URL couldn\'t be validated', 'code' => 2200],
        ], 400)]);

        $this->artisan('webhook:subscribe')->assertFailed();
    }

    public function test_subscribe_requires_a_verify_token(): void
    {
        config(['meta.webhook_verify_token' => null]);
        $this->fakeGraph();

        $this->artisan('webhook:subscribe')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_subscribe_requires_facebook_credentials(): void
    {
        config(['services.facebook.client_secret' => null]);
        $this->fakeGraph();

        $this->artisan('webhook:subscribe')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_status_lists_the_registered_subscription(): void
    {
        $this->fakeGraph();

        $this->artisan('webhook:status')
            ->expectsOutputToContain('https://tunnel.test/api/webhook')
            ->assertSuccessful();
    }

    public function test_status_fails_when_nothing_is_registered(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => []])]);

        $this->artisan('webhook:status')->assertFailed();
    }

    private function fakeGraph(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['success' => true, 'data' => [[
            'object' => 'page',
            'callback_url' => 'https://tunnel.test/api/webhook',
            'active' => true,
            'fields' => [['name' => 'feed'], ['name' => 'messages'], ['name' => 'messaging_postbacks']],
        ]]])]);
    }
}
