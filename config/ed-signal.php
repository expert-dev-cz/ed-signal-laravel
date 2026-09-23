<?php

return [
    'enabled' => env('ED_SIGNAL_ENABLED', true),

    'collector_base_url' => env('ED_SIGNAL_COLLECTOR_URL', ''),
    'site_id' => env('ED_SIGNAL_SITE_ID', ''),
    'key_id' => env('ED_SIGNAL_KEY_ID', ''),
    'secret' => env('ED_SIGNAL_SECRET', ''),

    'request_timeout' => (int) env('ED_SIGNAL_REQUEST_TIMEOUT', 10),
    'queue' => env('ED_SIGNAL_QUEUE', null),
    'queue_connection' => env('ED_SIGNAL_QUEUE_CONNECTION', null),
    'dispatch_mode' => env('ED_SIGNAL_DISPATCH_MODE', 'queue'), // queue|sync
    'queue_fallback_to_sync' => env('ED_SIGNAL_QUEUE_FALLBACK_TO_SYNC', true),
    'suppress_dispatch_exceptions' => env('ED_SIGNAL_SUPPRESS_DISPATCH_EXCEPTIONS', true),
    'send_server_events' => env('ED_SIGNAL_SEND_SERVER_EVENTS', true),

    'browser' => [
        'enabled' => env('ED_SIGNAL_BROWSER_SDK', true),
        'sdk_url' => env('ED_SIGNAL_SDK_URL', ''),
        'mode' => env('ED_SIGNAL_BROWSER_MODE', 'middleware'), // middleware|blade
        'inject_on_html' => true,
        'consent_source' => 'laravel_adapter',
    ],

    'tracking' => [
        'use_middleware' => env('ED_SIGNAL_USE_MIDDLEWARE', true),
        'track_page_view_server' => true,
        'track_form_submit_server' => true,
        'track_auth_events_server' => true,
        'exclude_paths' => [
            'admin*',
            'nova*',
            'horizon*',
            'telescope*',
            '_debugbar*',
            'livewire*',
            'api*',
            'sanctum*',
            'up',
            'health*',
        ],
    ],

    'cookies' => [
        'visitor_cookie' => 'ed_signal_vid',
        'session_cookie' => 'ed_signal_sid',
        'session_ts_cookie' => 'ed_signal_sts',
        'session_idle_seconds' => 1800,
        'visitor_ttl_minutes' => 568800,
        'session_ttl_minutes' => 1440,
        'secure' => null,
        'same_site' => 'Lax',
    ],

    'consent' => [
        // Same consent bar can write this cookie as JSON, e.g. {"analytics":true,"marketing":false,"preferences":false}
        'cookie_name' => env('ED_SIGNAL_CONSENT_COOKIE', 'ed_consent_state'),
        'receipt_cookie_name' => env('ED_SIGNAL_CONSENT_RECEIPT_COOKIE', 'ed_measurement_consent_receipt_token'),
        'default' => [
            'analytics' => false,
            'marketing' => false,
            'preferences' => false,
        ],
    ],

    'privacy' => [
        'blocked_key_fragments' => ['email', 'phone', 'name', 'address', 'message'],
    ],

    'country' => [
        'header_keys' => [
            'CF-IPCountry',
            'GEOIP_COUNTRY_CODE',
            'X-Country-Code',
            'X-AppEngine-Country',
        ],
    ],
];
