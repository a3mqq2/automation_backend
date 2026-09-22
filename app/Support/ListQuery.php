<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final readonly class ListQuery
{
    public function __construct(
        public string $sort,
        public string $direction,
        public int $perPage,
        public ?string $search = null,
        public array $filters = [],
    ) {
    }

    public function hasSearch(): bool
    {
        return $this->search !== null && trim($this->search) !== '';
    }

    public function searchPattern(): string
    {
        return '%'.addcslashes(trim((string) $this->search), '%_\\').'%';
    }

    public function hasFilter(string $key): bool
    {
        return array_key_exists($key, $this->filters)
            && $this->filters[$key] !== null
            && $this->filters[$key] !== '';
    }

    public function filter(string $key, mixed $default = null): mixed
    {
        return $this->hasFilter($key) ? $this->filters[$key] : $default;
    }

    public function paginate(Builder|Relation $query, array $sortColumns = []): LengthAwarePaginator
    {
        $sortColumn = $sortColumns[$this->sort] ?? $query->getModel()->qualifyColumn($this->sort);

        return $query
            ->orderBy($sortColumn, $this->direction)
            ->orderBy($query->getModel()->getQualifiedKeyName(), $this->direction)
            ->paginate($this->perPage)
            ->withQueryString();
    }
}
