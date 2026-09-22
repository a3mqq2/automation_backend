<?php

namespace App\Services\Catalog;

final readonly class CatalogItem
{
    public function __construct(
        public string $id,
        public string $title,
        public string $subtitle,
        public ?string $imageUrl,
        public ?string $url,
    ) {
    }
}
