<?php

return [
    'auth' => [
        'unauthenticated' => 'Your session has ended. Please sign in again.',
        'forbidden' => 'You do not have permission to perform this action.',
        'invalid_credentials' => 'The email or password is incorrect.',
        'facebook_failed' => 'Signing in with Facebook failed. Please try again.',
        'invalid_state' => 'The sign-in request has expired or is invalid. Please start again.',
    ],
    'subscription' => [
        'inactive' => 'Your subscription is not active. Please activate a license key to continue.',
    ],
    'license_key' => [
        'invalid' => 'This license key does not exist.',
        'already_used' => 'This license key has already been used.',
        'expired' => 'This license key has expired.',
        'does_not_extend' => 'This license key expires before your current subscription.',
        'used_cannot_be_deleted' => 'A license key that has been used cannot be deleted.',
    ],
    'facebook' => [
        'token_expired' => 'Your Facebook authorization has expired. Please sign in with Facebook again.',
        'request_failed' => 'Facebook did not respond as expected. Please try again later.',
    ],
    'page' => [
        'not_available' => 'This page is not available in your Facebook account.',
        'connected_by_another_account' => 'This page is already connected by another account.',
        'not_connected' => 'This page is not connected.',
    ],
    'flow' => [
        'not_publishable' => 'This flow still has problems that must be fixed before publishing.',
    ],
    'product' => [
        'post_already_linked' => 'This post is already linked to another product.',
    ],
    'resource' => [
        'not_found' => 'The requested resource was not found.',
    ],
    'request' => [
        'method_not_allowed' => 'This request method is not supported.',
        'too_many_attempts' => 'Too many attempts. Please wait a moment and try again.',
    ],
    'validation' => [
        'failed' => 'The submitted data is invalid.',
    ],
    'webhook' => [
        'invalid_signature' => 'The webhook signature is invalid.',
        'verification_failed' => 'Webhook verification failed.',
    ],
    'server' => [
        'error' => 'An unexpected error occurred. Please try again later.',
    ],
];
