<?php

return [

    'graph_base_url' => env('META_GRAPH_BASE_URL', 'https://graph.facebook.com'),

    'graph_version' => env('META_GRAPH_VERSION', 'v23.0'),

    'graph_timeout' => (int) env('META_GRAPH_TIMEOUT', 15),

    'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),

    'log_webhook_payloads' => (bool) env('META_LOG_WEBHOOK_PAYLOADS', false),

    'login_scopes' => [
        'email',
        'public_profile',
        'pages_show_list',
        'pages_read_engagement',
        'pages_read_user_content',
        'pages_manage_metadata',
        'pages_manage_engagement',
        'pages_messaging',
        'business_management',
    ],

    'page_subscribed_fields' => [
        'feed',
        'messages',
        'messaging_postbacks',
    ],

    'oauth_state_ttl_minutes' => 10,

    'media' => [
        'disk' => env('META_MEDIA_DISK', 'public'),
        'directory' => 'bot-media',
        'max_kilobytes' => (int) env('META_MEDIA_MAX_KILOBYTES', 8192),
        'mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    ],

    'comment_polling' => [
        'enabled' => (bool) env('META_COMMENT_POLLING', false),
        'posts_limit' => (int) env('META_COMMENT_POLLING_POSTS', 10),
        'comments_limit' => (int) env('META_COMMENT_POLLING_COMMENTS', 25),
        'max_lookback_minutes' => (int) env('META_COMMENT_POLLING_LOOKBACK_MINUTES', 60),
        'overlap_seconds' => 60,
    ],

];
