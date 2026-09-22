<?php

namespace Tests\Feature\Foundation;

use App\Enums\ErrorCode;
use App\Models\FacebookPage;
use Database\Factories\BotFlowFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_error_code_is_translated_in_both_languages(): void
    {
        foreach (['en', 'ar'] as $locale) {
            foreach (ErrorCode::cases() as $errorCode) {
                $this->assertNotSame(
                    $errorCode->translationKey(),
                    trans($errorCode->translationKey(), [], $locale),
                    "Missing {$locale} translation for {$errorCode->value}",
                );
            }
        }
    }

    public function test_arabic_and_english_language_files_have_the_same_keys(): void
    {
        foreach (['errors', 'validation', 'auth', 'pagination', 'passwords', 'data_deletion', 'flow'] as $file) {
            $english = array_keys(Arr::dot(require lang_path("en/{$file}.php")));
            $arabic = array_keys(Arr::dot(require lang_path("ar/{$file}.php")));

            $this->assertEqualsCanonicalizing(
                array_values(array_filter($english, fn (string $key) => ! str_starts_with($key, 'custom'))),
                array_values(array_filter($arabic, fn (string $key) => ! str_starts_with($key, 'custom'))),
                "Language file {$file}.php differs between en and ar",
            );
        }
    }

    public function test_validation_errors_use_arabic_messages_and_attribute_names(): void
    {
        $this->actingAsClient();

        $this->postJson('/api/rules', [], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation.failed')
            ->assertJsonPath('message', 'البيانات المرسلة غير صالحة.')
            ->assertJsonPath('errors.name.0', 'حقل الاسم مطلوب.')
            ->assertJsonPath('errors.facebook_page_id.0', 'حقل صفحة فيسبوك مطلوب.');
    }

    public function test_flow_validation_errors_are_arabic(): void
    {
        $page = FacebookPage::factory()->for($this->actingAsClient())->create();

        $this->postJson('/api/bot-flows', [
            'facebook_page_id' => $page->id,
            'name' => 'قائمة',
            'flow_json' => ['entry' => []],
        ], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['flow_json.nodes']);

        $errors = $this->postJson('/api/bot-flows', [
            'facebook_page_id' => $page->id,
            'name' => 'قائمة',
            'flow_json' => ['entry' => []],
        ], ['Accept-Language' => 'ar'])->json('errors');

        $this->assertSame('حقل خطوات التدفق مطلوب.', $errors['flow_json.nodes'][0]);
    }

    public function test_flow_issue_messages_are_translated(): void
    {
        $page = FacebookPage::factory()->for($this->actingAsClient())->create();
        $definition = BotFlowFactory::sampleDefinition();
        $definition['nodes'][1]['type'] = 'teleport';

        $this->postJson('/api/bot-flows', [
            'facebook_page_id' => $page->id,
            'name' => 'قائمة',
            'flow_json' => $definition,
        ], ['Accept-Language' => 'ar'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.flow_json.0', 'prices: نوع هذه الخطوة غير معروف.');
    }

    public function test_english_validation_errors_use_friendly_attribute_names(): void
    {
        $this->actingAsClient();

        $this->postJson('/api/rules', [])
            ->assertJsonPath('errors.facebook_page_id.0', 'The Facebook page field is required.');
    }
}
