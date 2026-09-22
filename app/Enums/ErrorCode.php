<?php

namespace App\Enums;

enum ErrorCode: string
{
    case Unauthenticated = 'auth.unauthenticated';
    case Forbidden = 'auth.forbidden';
    case InvalidCredentials = 'auth.invalid_credentials';
    case FacebookLoginFailed = 'auth.facebook_failed';
    case InvalidOAuthState = 'auth.invalid_state';
    case SubscriptionInactive = 'subscription.inactive';
    case LicenseKeyInvalid = 'license_key.invalid';
    case LicenseKeyAlreadyUsed = 'license_key.already_used';
    case LicenseKeyExpired = 'license_key.expired';
    case LicenseKeyDoesNotExtend = 'license_key.does_not_extend';
    case LicenseKeyUsedCannotBeDeleted = 'license_key.used_cannot_be_deleted';
    case FacebookTokenExpired = 'facebook.token_expired';
    case FacebookRequestFailed = 'facebook.request_failed';
    case PageNotAvailable = 'page.not_available';
    case PageConnectedByAnotherAccount = 'page.connected_by_another_account';
    case PageNotConnected = 'page.not_connected';
    case FlowNotPublishable = 'flow.not_publishable';
    case PostAlreadyLinked = 'product.post_already_linked';
    case ResourceNotFound = 'resource.not_found';
    case MethodNotAllowed = 'request.method_not_allowed';
    case TooManyAttempts = 'request.too_many_attempts';
    case ValidationFailed = 'validation.failed';
    case WebhookInvalidSignature = 'webhook.invalid_signature';
    case WebhookVerificationFailed = 'webhook.verification_failed';
    case ServerError = 'server.error';

    public function status(): int
    {
        return match ($this) {
            self::Unauthenticated, self::FacebookTokenExpired => 401,
            self::Forbidden, self::SubscriptionInactive, self::WebhookInvalidSignature, self::WebhookVerificationFailed => 403,
            self::PageNotAvailable, self::ResourceNotFound => 404,
            self::MethodNotAllowed => 405,
            self::PageConnectedByAnotherAccount, self::PostAlreadyLinked => 409,
            self::TooManyAttempts => 429,
            self::FacebookRequestFailed => 502,
            self::ServerError => 500,
            default => 422,
        };
    }

    public function translationKey(): string
    {
        return 'errors.'.$this->value;
    }
}
