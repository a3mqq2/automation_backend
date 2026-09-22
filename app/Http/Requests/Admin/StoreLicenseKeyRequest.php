<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class StoreLicenseKeyRequest extends FormRequest
{
    public const MAX_QUANTITY = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expires_at' => ['required', 'date', 'after:now'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
        ];
    }

    public function expiresAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('expires_at'));
    }

    public function note(): ?string
    {
        $note = trim((string) $this->validated('note'));

        return $note === '' ? null : $note;
    }

    public function quantity(): int
    {
        return (int) $this->validated('quantity', 1);
    }
}
