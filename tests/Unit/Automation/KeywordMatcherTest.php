<?php

namespace Tests\Unit\Automation;

use App\Enums\MatchType;
use App\Services\Automation\Engine\KeywordMatcher;
use App\Services\Automation\Engine\TextNormalizer;
use PHPUnit\Framework\TestCase;

class KeywordMatcherTest extends TestCase
{
    private KeywordMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new KeywordMatcher(new TextNormalizer());
    }

    public function test_exact_match_requires_the_whole_normalized_text(): void
    {
        $this->assertTrue($this->matcher->matches(' Price ', ['price'], MatchType::Exact));
        $this->assertTrue($this->matcher->matches('بكم السعر؟', ['بكم السعر؟'], MatchType::Exact));
        $this->assertFalse($this->matcher->matches('what is the price', ['price'], MatchType::Exact));
    }

    public function test_contains_matches_anywhere_in_the_text(): void
    {
        $this->assertTrue($this->matcher->matches('كم أسعار المنتجات؟', ['اسعار'], MatchType::Contains));
        $this->assertTrue($this->matcher->matches('What is the PRICE?', ['delivery', 'price'], MatchType::Contains));
        $this->assertFalse($this->matcher->matches('hello there', ['price'], MatchType::Contains));
    }

    public function test_starts_with_matches_the_beginning_only(): void
    {
        $this->assertTrue($this->matcher->matches('Menu please', ['menu'], MatchType::StartsWith));
        $this->assertFalse($this->matcher->matches('show me the menu', ['menu'], MatchType::StartsWith));
    }

    public function test_any_matches_everything_including_empty_text(): void
    {
        $this->assertTrue($this->matcher->matches('', [], MatchType::Any));
        $this->assertTrue($this->matcher->matches('anything', [], MatchType::Any));
    }

    public function test_blank_keywords_and_blank_text_never_match(): void
    {
        $this->assertFalse($this->matcher->matches('price', ['', '   '], MatchType::Contains));
        $this->assertFalse($this->matcher->matches('', ['price'], MatchType::Contains));
        $this->assertFalse($this->matcher->matches('price', [], MatchType::Exact));
    }

    public function test_same_text_compares_normalized_values(): void
    {
        $this->assertTrue($this->matcher->sameText('الأسعار', 'الاسعار'));
        $this->assertTrue($this->matcher->sameText(' Prices ', 'prices'));
        $this->assertFalse($this->matcher->sameText('', ''));
        $this->assertFalse($this->matcher->sameText('Prices', 'Location'));
    }
}
