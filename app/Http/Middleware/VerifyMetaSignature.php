<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMetaSignature
{
    private const SIGNATURE_HEADER = 'X-Hub-Signature-256';

    private const SIGNATURE_PREFIX = 'sha256=';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.facebook.client_secret');
        $signature = (string) $request->header(self::SIGNATURE_HEADER, '');

        if ($secret === '' || ! str_starts_with($signature, self::SIGNATURE_PREFIX)) {
            throw new ApiException(ErrorCode::WebhookInvalidSignature);
        }

        $expectedSignature = self::SIGNATURE_PREFIX.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            throw new ApiException(ErrorCode::WebhookInvalidSignature);
        }

        return $next($request);
    }
}
