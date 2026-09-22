<?php

namespace App\Http\Requests;

use App\Support\ListQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

abstract class ListRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    abstract protected function sortableFields(): array;

    protected function defaultSort(): string
    {
        return 'created_at';
    }

    protected function defaultDirection(): string
    {
        return 'desc';
    }

    protected function filterRules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ($this->filterRules() as $field => $rules) {
            if (in_array('boolean', $rules, true) && is_string($this->query($field))) {
                $this->merge([
                    $field => filter_var($this->query($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->query($field),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return array_merge([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'sort' => ['sometimes', 'string', Rule::in($this->sortableFields())],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ], $this->filterRules());
    }

    public function listQuery(): ListQuery
    {
        return new ListQuery(
            sort: $this->validated('sort', $this->defaultSort()),
            direction: $this->validated('direction', $this->defaultDirection()),
            perPage: (int) $this->validated('per_page', self::DEFAULT_PER_PAGE),
            search: $this->validated('search'),
            filters: Arr::only($this->validated(), array_keys($this->filterRules())),
        );
    }
}
