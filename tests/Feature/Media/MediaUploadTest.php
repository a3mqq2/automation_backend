<?php

namespace Tests\Feature\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public', ['url' => 'https://api.test/storage']);
    }

    public function test_client_can_upload_an_image_and_gets_a_public_url(): void
    {
        $client = $this->actingAsClient();

        $response = $this->postJson('/api/media', ['file' => UploadedFile::fake()->image('menu.jpg', 800, 600)])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['path', 'url', 'mime_type', 'size']]);

        $path = $response->json('data.path');

        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith("bot-media/{$client->id}/", $path);
        $this->assertSame("https://api.test/storage/{$path}", $response->json('data.url'));
        $this->assertSame('image/jpeg', $response->json('data.mime_type'));
    }

    public function test_uploads_of_other_clients_are_kept_apart(): void
    {
        $first = $this->actingAsClient();
        $firstPath = $this->postJson('/api/media', ['file' => UploadedFile::fake()->image('a.png')])->json('data.path');

        $second = $this->actingAsClient();
        $secondPath = $this->postJson('/api/media', ['file' => UploadedFile::fake()->image('b.png')])->json('data.path');

        $this->assertStringStartsWith("bot-media/{$first->id}/", $firstPath);
        $this->assertStringStartsWith("bot-media/{$second->id}/", $secondPath);
    }

    public function test_non_images_and_oversized_files_are_rejected(): void
    {
        $this->actingAsClient();

        $this->postJson('/api/media', ['file' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation.failed')
            ->assertJsonValidationErrors('file');

        $this->postJson('/api/media', ['file' => UploadedFile::fake()->image('huge.jpg')->size(9000)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->postJson('/api/media', [])->assertJsonValidationErrors('file');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_client_can_delete_their_own_upload_only(): void
    {
        $owner = $this->actingAsClient();
        $path = $this->postJson('/api/media', ['file' => UploadedFile::fake()->image('menu.jpg')])->json('data.path');

        $this->actingAsClient(User::factory()->subscribed()->create());
        $this->deleteJson('/api/media', ['path' => $path])
            ->assertNotFound()
            ->assertJsonPath('code', 'resource.not_found');
        Storage::disk('public')->assertExists($path);

        $this->actingAsClient($owner);
        $this->deleteJson('/api/media', ['path' => $path])->assertNoContent();
        Storage::disk('public')->assertMissing($path);

        $this->deleteJson('/api/media', ['path' => $path])->assertNotFound();
        $this->deleteJson('/api/media', ['path' => '../../.env'])->assertNotFound();
    }

    public function test_uploads_require_an_active_subscription(): void
    {
        $this->actingAsClient(User::factory()->create());

        $this->postJson('/api/media', ['file' => UploadedFile::fake()->image('menu.jpg')])
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription.inactive');
    }

    public function test_admins_cannot_upload(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/media', ['file' => UploadedFile::fake()->image('menu.jpg')])->assertForbidden();
    }
}
