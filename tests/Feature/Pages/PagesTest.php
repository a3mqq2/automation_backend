<?php

namespace Tests\Feature\Pages;

use App\Models\AutomationRule;
use App\Models\BotFlow;
use App\Models\FacebookPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP_PAGE_ID = '111111111111';

    private const CAFE_PAGE_ID = '222222222222';

    public function test_available_pages_merge_facebook_data_with_connection_state(): void
    {
        $client = $this->actingAsClient();
        $otherClient = User::factory()->create();
        $ownRow = FacebookPage::factory()->for($client)->create(['page_id' => self::SHOP_PAGE_ID, 'name' => 'Old shop name']);
        FacebookPage::factory()->for($otherClient)->create(['page_id' => self::CAFE_PAGE_ID]);
        $this->fakeAccounts();

        $response = $this->getJson('/api/pages')->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.page_id', self::CAFE_PAGE_ID)
            ->assertJsonPath('data.0.is_connected', false)
            ->assertJsonPath('data.0.connected_by_another_account', true)
            ->assertJsonPath('data.0.can_connect', true)
            ->assertJsonPath('data.1.page_id', self::SHOP_PAGE_ID)
            ->assertJsonPath('data.1.is_connected', true)
            ->assertJsonPath('data.1.facebook_page_id', $ownRow->id)
            ->assertJsonPath('data.1.picture_url', 'https://cdn.example.com/shop.jpg');

        $this->assertStringNotContainsString('page-token', $response->getContent());

        $ownRow->refresh();

        $this->assertSame('Shop', $ownRow->name);
        $this->assertSame('shop-page-token', $ownRow->page_access_token);
    }

    public function test_pages_without_required_tasks_cannot_be_connected(): void
    {
        $this->actingAsClient();
        Http::fake([
            'graph.facebook.com/v23.0/me/accounts*' => Http::response(['data' => [
                ['id' => self::SHOP_PAGE_ID, 'name' => 'Shop', 'access_token' => 'shop-page-token', 'tasks' => ['ANALYZE']],
            ]]),
        ]);

        $this->getJson('/api/pages')->assertJsonPath('data.0.can_connect', false);
        $this->postJson('/api/pages/'.self::SHOP_PAGE_ID.'/connect')
            ->assertNotFound()
            ->assertJsonPath('code', 'page.not_available');
    }

    public function test_expired_facebook_token_is_reported_before_calling_facebook(): void
    {
        $this->actingAsClient(User::factory()->subscribed()->withExpiredFacebookToken()->create());
        Http::fake();

        $this->getJson('/api/pages')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'facebook.token_expired');

        Http::assertNothingSent();
    }

    public function test_facebook_token_errors_map_to_token_expired(): void
    {
        $this->actingAsClient();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Session has expired', 'code' => 190]], 400),
        ]);

        $this->getJson('/api/pages')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'facebook.token_expired');
    }

    public function test_other_facebook_errors_map_to_request_failed(): void
    {
        $this->actingAsClient();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Unknown error', 'code' => 1]], 500),
        ]);

        $this->getJson('/api/pages')
            ->assertStatus(502)
            ->assertJsonPath('code', 'facebook.request_failed');
    }

    public function test_client_can_connect_a_page(): void
    {
        $client = $this->actingAsClient();
        $this->fakeAccounts();

        $this->postJson('/api/pages/'.self::SHOP_PAGE_ID.'/connect')
            ->assertCreated()
            ->assertJsonPath('data.page_id', self::SHOP_PAGE_ID)
            ->assertJsonPath('data.name', 'Shop')
            ->assertJsonPath('data.is_connected', true)
            ->assertJsonPath('data.automation_rules_count', 0)
            ->assertJsonMissingPath('data.page_access_token');

        $page = $client->facebookPages()->firstOrFail();

        $this->assertTrue($page->is_connected);
        $this->assertSame('shop-page-token', $page->page_access_token);
        $this->assertNotSame('shop-page-token', $page->getRawOriginal('page_access_token'));

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_contains($request->url(), self::SHOP_PAGE_ID.'/subscribed_apps')
            && str_contains($request->url(), 'access_token=shop-page-token')
            && $request['subscribed_fields'] === 'feed,messages,messaging_postbacks');
    }

    public function test_connecting_a_page_outside_the_account_fails(): void
    {
        $this->actingAsClient();
        $this->fakeAccounts();

        $this->postJson('/api/pages/999999999/connect')
            ->assertNotFound()
            ->assertJsonPath('code', 'page.not_available');

        $this->assertSame(0, FacebookPage::query()->count());
    }

    public function test_page_connected_by_another_account_cannot_be_connected(): void
    {
        $this->actingAsClient();
        FacebookPage::factory()->for(User::factory()->create())->create(['page_id' => self::SHOP_PAGE_ID]);
        $this->fakeAccounts();

        $this->postJson('/api/pages/'.self::SHOP_PAGE_ID.'/connect')
            ->assertConflict()
            ->assertJsonPath('code', 'page.connected_by_another_account');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'subscribed_apps'));
    }

    public function test_page_released_by_another_account_can_be_connected(): void
    {
        $client = $this->actingAsClient();
        $previousOwner = User::factory()->create();
        $previousRow = FacebookPage::factory()->disconnected()->for($previousOwner)->create(['page_id' => self::SHOP_PAGE_ID]);
        AutomationRule::factory()->create(['facebook_page_id' => $previousRow->id]);
        $this->fakeAccounts();

        $this->postJson('/api/pages/'.self::SHOP_PAGE_ID.'/connect')->assertCreated();

        $this->assertSame(2, FacebookPage::query()->where('page_id', self::SHOP_PAGE_ID)->count());
        $this->assertSame(0, $client->automationRules()->count());
        $this->assertFalse($previousRow->refresh()->is_connected);
    }

    public function test_reconnecting_reuses_the_existing_row(): void
    {
        $client = $this->actingAsClient();
        $row = FacebookPage::factory()->disconnected()->for($client)->create(['page_id' => self::SHOP_PAGE_ID]);
        AutomationRule::factory()->create(['facebook_page_id' => $row->id]);
        $this->fakeAccounts();

        $this->postJson('/api/pages/'.self::SHOP_PAGE_ID.'/connect')
            ->assertOk()
            ->assertJsonPath('data.id', $row->id)
            ->assertJsonPath('data.automation_rules_count', 1);

        $this->assertTrue($row->refresh()->is_connected);
    }

    public function test_client_can_disconnect_a_page(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create(['page_id' => self::SHOP_PAGE_ID, 'page_access_token' => 'shop-page-token']);
        Http::fake(['graph.facebook.com/*' => Http::response(['success' => true])]);

        $this->deleteJson('/api/pages/'.self::SHOP_PAGE_ID)->assertNoContent();

        $page->refresh();

        $this->assertFalse($page->is_connected);
        $this->assertNull($page->page_access_token);

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && str_contains($request->url(), self::SHOP_PAGE_ID.'/subscribed_apps'));
    }

    public function test_disconnect_still_succeeds_when_facebook_rejects_the_unsubscribe(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create(['page_id' => self::SHOP_PAGE_ID]);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token', 'code' => 190]], 400)]);

        $this->deleteJson('/api/pages/'.self::SHOP_PAGE_ID)->assertNoContent();

        $this->assertFalse($page->refresh()->is_connected);
    }

    public function test_disconnecting_a_page_that_is_not_connected_fails(): void
    {
        $client = $this->actingAsClient();
        FacebookPage::factory()->disconnected()->for($client)->create(['page_id' => self::SHOP_PAGE_ID]);

        $this->deleteJson('/api/pages/'.self::SHOP_PAGE_ID)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'page.not_connected');

        $this->deleteJson('/api/pages/'.self::CAFE_PAGE_ID)->assertJsonPath('code', 'page.not_connected');
    }

    public function test_client_cannot_disconnect_another_clients_page(): void
    {
        $this->actingAsClient();
        $foreignPage = FacebookPage::factory()->for(User::factory()->create())->create(['page_id' => self::SHOP_PAGE_ID]);

        $this->deleteJson('/api/pages/'.self::SHOP_PAGE_ID)->assertJsonPath('code', 'page.not_connected');

        $this->assertTrue($foreignPage->refresh()->is_connected);
    }

    public function test_connected_pages_list_is_scoped_sorted_and_counted(): void
    {
        $client = $this->actingAsClient();
        $zeta = FacebookPage::factory()->for($client)->create(['name' => 'Zeta Store']);
        $alpha = FacebookPage::factory()->for($client)->create(['name' => 'Alpha Store']);
        FacebookPage::factory()->disconnected()->for($client)->create(['name' => 'Beta Store']);
        FacebookPage::factory()->for(User::factory()->create())->create(['name' => 'Foreign Store']);
        AutomationRule::factory()->count(2)->create(['facebook_page_id' => $alpha->id]);
        AutomationRule::factory()->inactive()->create(['facebook_page_id' => $alpha->id]);
        BotFlow::factory()->create(['facebook_page_id' => $alpha->id]);

        $this->getJson('/api/pages/connected')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $alpha->id)
            ->assertJsonPath('data.0.automation_rules_count', 3)
            ->assertJsonPath('data.0.active_automation_rules_count', 2)
            ->assertJsonPath('data.0.bot_flows_count', 1)
            ->assertJsonPath('data.1.id', $zeta->id);

        $this->getJson('/api/pages/connected?search=zeta')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $zeta->id);
    }

    public function test_page_detail_is_scoped_to_the_client(): void
    {
        $client = $this->actingAsClient();
        $page = FacebookPage::factory()->for($client)->create(['page_id' => self::SHOP_PAGE_ID]);
        FacebookPage::factory()->for(User::factory()->create())->create(['page_id' => self::CAFE_PAGE_ID]);

        $this->getJson('/api/pages/'.self::SHOP_PAGE_ID)
            ->assertOk()
            ->assertJsonPath('data.id', $page->id)
            ->assertJsonPath('data.conversations_count', 0)
            ->assertJsonPath('data.activity_logs_count', 0);

        $this->getJson('/api/pages/'.self::CAFE_PAGE_ID)
            ->assertNotFound()
            ->assertJsonPath('code', 'resource.not_found');
    }

    public function test_pages_require_an_active_subscription(): void
    {
        $this->actingAsClient(User::factory()->withExpiredSubscription()->create());

        $this->getJson('/api/pages')->assertForbidden()->assertJsonPath('code', 'subscription.inactive');
        $this->postJson('/api/pages/'.self::SHOP_PAGE_ID.'/connect')->assertForbidden();
        $this->getJson('/api/pages/connected')->assertForbidden();
    }

    private function fakeAccounts(): void
    {
        Http::fake([
            'graph.facebook.com/v23.0/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => self::SHOP_PAGE_ID,
                        'name' => 'Shop',
                        'category' => 'Shopping & retail',
                        'picture' => ['data' => ['url' => 'https://cdn.example.com/shop.jpg']],
                        'tasks' => ['ANALYZE', 'ADVERTISE', 'MODERATE', 'CREATE_CONTENT', 'MESSAGING', 'MANAGE'],
                        'access_token' => 'shop-page-token',
                    ],
                    [
                        'id' => self::CAFE_PAGE_ID,
                        'name' => 'Cafe',
                        'category' => 'Restaurant',
                        'tasks' => ['MODERATE', 'MESSAGING'],
                        'access_token' => 'cafe-page-token',
                    ],
                ],
            ]),
            'graph.facebook.com/v23.0/*/subscribed_apps*' => Http::response(['success' => true]),
        ]);
    }
}
