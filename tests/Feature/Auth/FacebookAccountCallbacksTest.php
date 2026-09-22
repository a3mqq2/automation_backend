<?php

namespace Tests\Feature\Auth;

use App\Models\ActivityLog;
use App\Models\AutomationRule;
use App\Models\BotFlow;
use App\Models\Conversation;
use App\Models\DataDeletionRequest;
use App\Models\FacebookPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacebookAccountCallbacksTest extends TestCase
{
    use RefreshDatabase;

    public function test_deauthorize_disconnects_pages_and_revokes_tokens(): void
    {
        $client = User::factory()->subscribed()->create(['fb_user_id' => '10150000000001']);
        $page = FacebookPage::factory()->for($client)->create(['page_access_token' => 'page-token']);
        $rule = AutomationRule::factory()->create(['facebook_page_id' => $page->id]);
        $client->createToken('client');

        $this->post('/api/facebook/deauthorize', ['signed_request' => $this->signedRequest(['user_id' => '10150000000001'])])
            ->assertNoContent();

        $client->refresh();
        $page->refresh();

        $this->assertFalse($page->is_connected);
        $this->assertNull($page->page_access_token);
        $this->assertNull($client->fb_access_token);
        $this->assertNull($client->token_expires_at);
        $this->assertSame(0, $client->tokens()->count());
        $this->assertModelExists($client);
        $this->assertModelExists($rule);
        $this->assertTrue($client->hasActiveSubscription());
    }

    public function test_deauthorize_ignores_unknown_accounts(): void
    {
        $this->post('/api/facebook/deauthorize', ['signed_request' => $this->signedRequest(['user_id' => '999'])])
            ->assertNoContent();
    }

    public function test_deauthorize_rejects_a_forged_signature(): void
    {
        $client = User::factory()->create(['fb_user_id' => '10150000000001']);
        $forged = $this->signedRequest(['user_id' => '10150000000001'], 'wrong-secret');

        $this->postJson('/api/facebook/deauthorize', ['signed_request' => $forged])
            ->assertForbidden()
            ->assertJsonPath('code', 'webhook.invalid_signature');

        $this->postJson('/api/facebook/deauthorize', ['signed_request' => 'not-a-signed-request'])->assertForbidden();
        $this->postJson('/api/facebook/deauthorize', [])->assertForbidden();

        $this->assertNotNull($client->refresh()->fb_access_token);
    }

    public function test_deauthorize_rejects_an_unexpected_algorithm(): void
    {
        $signedRequest = $this->signedRequest(['user_id' => '10150000000001', 'algorithm' => 'MD5']);

        $this->postJson('/api/facebook/deauthorize', ['signed_request' => $signedRequest])->assertForbidden();
    }

    public function test_data_deletion_removes_every_trace_of_the_client(): void
    {
        $client = User::factory()->subscribed()->create(['fb_user_id' => '10150000000001']);
        $page = FacebookPage::factory()->for($client)->create();
        $rule = AutomationRule::factory()->create(['facebook_page_id' => $page->id]);
        $flow = BotFlow::factory()->create(['facebook_page_id' => $page->id]);
        $conversation = Conversation::factory()->create(['facebook_page_id' => $page->id]);
        $log = ActivityLog::factory()->create(['facebook_page_id' => $page->id]);
        $client->createToken('client');
        $otherClient = User::factory()->create();
        $otherPage = FacebookPage::factory()->for($otherClient)->create();

        $response = $this->postJson('/api/facebook/data-deletion', [
            'signed_request' => $this->signedRequest(['user_id' => '10150000000001']),
        ])->assertOk()->assertJsonStructure(['url', 'confirmation_code']);

        $confirmationCode = $response->json('confirmation_code');

        $this->assertStringContainsString('/api/facebook/data-deletion/status?code='.$confirmationCode, $response->json('url'));
        $this->assertModelMissing($client);
        $this->assertModelMissing($page);
        $this->assertModelMissing($rule);
        $this->assertModelMissing($flow);
        $this->assertModelMissing($conversation);
        $this->assertModelMissing($log);
        $this->assertModelExists($otherClient);
        $this->assertModelExists($otherPage);
        $this->assertSame(0, $client->tokens()->count());

        $this->getJson('/api/facebook/data-deletion/status?code='.$confirmationCode)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.confirmation_code', $confirmationCode);
    }

    public function test_data_deletion_works_for_an_account_with_no_data(): void
    {
        $this->postJson('/api/facebook/data-deletion', ['signed_request' => $this->signedRequest(['user_id' => '404404'])])
            ->assertOk();

        $this->assertSame('404404', DataDeletionRequest::query()->sole()->fb_user_id);
    }

    public function test_data_deletion_rejects_a_forged_signature(): void
    {
        $this->postJson('/api/facebook/data-deletion', ['signed_request' => $this->signedRequest(['user_id' => '1'], 'wrong-secret')])
            ->assertForbidden()
            ->assertJsonPath('code', 'webhook.invalid_signature');

        $this->assertSame(0, DataDeletionRequest::query()->count());
    }

    public function test_deletion_status_reports_arabic_message_and_unknown_codes(): void
    {
        $this->postJson('/api/facebook/data-deletion', ['signed_request' => $this->signedRequest(['user_id' => '555'])]);
        $confirmationCode = DataDeletionRequest::query()->sole()->confirmation_code;

        $this->getJson('/api/facebook/data-deletion/status?code='.$confirmationCode, ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.message', 'تم حذف كل البيانات المرتبطة بحسابك على فيسبوك من المنصة.');

        $this->getJson('/api/facebook/data-deletion/status?code=unknown')
            ->assertNotFound()
            ->assertJsonPath('code', 'resource.not_found');
    }

    private function signedRequest(array $payload, ?string $secret = null): string
    {
        $payload = array_merge(['algorithm' => 'HMAC-SHA256', 'issued_at' => now()->timestamp], $payload);
        $encodedPayload = $this->encode(json_encode($payload));
        $signature = hash_hmac('sha256', $encodedPayload, $secret ?? config('services.facebook.client_secret'), true);

        return $this->encode($signature).'.'.$encodedPayload;
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
