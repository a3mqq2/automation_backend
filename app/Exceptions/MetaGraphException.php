<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaGraphException extends ApiException
{
    private const TOKEN_ERROR_CODES = [102, 190];

    public function __construct(
        ErrorCode $errorCode,
        public readonly array $graphError = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, previous: $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $graphError = (array) ($response->json('error') ?? []);
        $graphError['http_status'] = $response->status();

        return new self(self::errorCodeFor($graphError), $graphError);
    }

    public static function fromThrowable(Throwable $throwable): self
    {
        return new self(ErrorCode::FacebookRequestFailed, ['message' => $throwable->getMessage()], $throwable);
    }

    public function graphErrorCode(): ?int
    {
        return isset($this->graphError['code']) ? (int) $this->graphError['code'] : null;
    }

    public function graphMessage(): string
    {
        return (string) ($this->graphError['message'] ?? $this->getMessage());
    }

    public function isTokenError(): bool
    {
        return $this->errorCode === ErrorCode::FacebookTokenExpired;
    }

    public function report(): void
    {
        Log::warning('Meta Graph API request failed', $this->graphError);
    }

    private static function errorCodeFor(array $graphError): ErrorCode
    {
        return in_array((int) ($graphError['code'] ?? 0), self::TOKEN_ERROR_CODES, true)
            ? ErrorCode::FacebookTokenExpired
            : ErrorCode::FacebookRequestFailed;
    }
}
