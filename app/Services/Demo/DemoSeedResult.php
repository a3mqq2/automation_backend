<?php

namespace App\Services\Demo;

use App\Models\FacebookPage;

final readonly class DemoSeedResult
{
    public function __construct(
        public FacebookPage $page,
        public int $categories,
        public int $brands,
        public int $products,
        public int $commentRules,
        public int $messageRules,
        public array $flows,
        public array $linkedPosts,
        public ?string $postLinkingError,
    ) {
    }
}
