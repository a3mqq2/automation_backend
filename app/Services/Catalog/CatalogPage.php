<?php

namespace App\Services\Catalog;

final readonly class CatalogPage
{
    public function __construct(
        public array $items,
        public int $offset,
        public int $total,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function nextOffset(): ?int
    {
        $next = $this->offset + count($this->items);

        return $next < $this->total ? $next : null;
    }
}
