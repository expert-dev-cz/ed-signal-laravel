# ED Signal Laravel Adapter

Reusable Laravel package for custom (non-WordPress) websites. It mirrors the WordPress adapter behavior: automatic browser SDK init, server-side event delivery, consent mode handling, attribution, anonymous visitor/session IDs, and anonymized country enrichment.

## Compatibility

- PHP 8.1+
- Laravel 9, 10, 11, 12, 13

## Features

- Automatic tracking right after installation (no template edits required)
- Browser SDK auto-injection into HTML responses
- Server-side signed event delivery to `/v1/server-events`
- `consent_mode` split (`with_consent` / `without_consent`)
- UTM, referrer, GCLID/GBRAID/WBRAID extraction
- Anonymous country from proxy/CDN headers
- Login and sign-up server events from Laravel auth events
- Optional server page view and form submit events
- PII-safe event data sanitization
- Queue-based retries through Laravel jobs

## Installation

```bash
composer require expertdev/ed-signal-laravel
php artisan ed-signal:install
```

## Environment

```env
ED_SIGNAL_ENABLED=true
ED_SIGNAL_DEBUG=false
ED_SIGNAL_COLLECTOR_URL=https://tracking.example.com/api
ED_SIGNAL_SITE_ID=your_site_id
ED_SIGNAL_KEY_ID=your_key_id
ED_SIGNAL_SECRET=your_secret

ED_SIGNAL_BROWSER_SDK=true
ED_SIGNAL_SDK_URL=https://tracking.example.com/measurement/v1/measurement.js
ED_SIGNAL_BROWSER_MODE=middleware
ED_SIGNAL_USE_MIDDLEWARE=true

ED_SIGNAL_QUEUE=default
ED_SIGNAL_REQUEST_TIMEOUT=10
ED_SIGNAL_DISPATCH_MODE=queue
ED_SIGNAL_QUEUE_FALLBACK_TO_SYNC=true
ED_SIGNAL_SUPPRESS_DISPATCH_EXCEPTIONS=true

# Same cookie bar across Laravel websites
ED_SIGNAL_CONSENT_COOKIE=ed_consent_state
ED_SIGNAL_CONSENT_RECEIPT_COOKIE=ed_measurement_consent_receipt_token
```

### Queue Safety (Important)

If your production does not have `phpredis` (or Redis queue client), do one of these:

```env
# Safe fallback mode (recommended)
ED_SIGNAL_DISPATCH_MODE=queue
ED_SIGNAL_QUEUE_CONNECTION=redis
ED_SIGNAL_QUEUE_FALLBACK_TO_SYNC=true
ED_SIGNAL_SUPPRESS_DISPATCH_EXCEPTIONS=true
```

or force synchronous sending:

```env
ED_SIGNAL_DISPATCH_MODE=sync
ED_SIGNAL_QUEUE_CONNECTION=
```

This prevents authentication/login requests from failing when queue dispatch throws runtime errors.

## Full Page Cache Mode

If your project serves cached HTML outside Laravel middleware, switch to Blade mode:

```env
ED_SIGNAL_BROWSER_MODE=blade
ED_SIGNAL_USE_MIDDLEWARE=false
```

Then place this in your main layout before `</head>` or before `</body>`:

```blade
@edSignalScripts
```

Notes:

- In Blade mode, browser-side tracking still works on cached pages.
- Automatic server `page_view` / `form_submit` from middleware is disabled, because middleware is bypassed by full page cache.
- Manual server events via `EdSignal::event(...)` still work normally.

## How It Works

- Middleware is auto-attached to `web` group by package provider.
- It creates first-party anonymous cookies:
  - `ed_signal_vid`
  - `ed_signal_sid`
  - `ed_signal_sts`
- It injects the browser SDK snippet and initializes `window.ExpertMeasurement.init(...)`.
- It sends server-side events through queue job `SendServerEvent`.

## Default Auto-Traced Events

- Browser SDK events (from measurement.js)
  - `page_view`, click interactions, scroll depth, form interactions, SPA navigation hooks
- Server events
  - `page_view` for HTML GET/HEAD requests
  - `form_submit` for HTML POST requests
  - `login` (Laravel `Login` event)
  - `sign_up` (Laravel `Registered` event)

## Manual Tracking API

```php
use ExpertDev\EdSignalLaravel\Facades\EdSignal;

EdSignal::event('generate_lead', [
    'lead_source' => 'contact_form',
]);

EdSignal::purchase(
    transactionId: (string) $order->id,
    value: (float) $order->total,
    currency: 'CZK',
    items: [
        ['item_id' => 'sku-123', 'item_name' => 'Product', 'price' => 999, 'quantity' => 1],
    ],
);
```

## Connection Test

```bash
php artisan ed-signal:test
```

Verbose diagnostics:

```bash
php artisan ed-signal:test --vv
```

This prints endpoint, HTTP status, response body, and missing configuration hints.

With `--vv`, it also prints the runtime dispatch mode, resolved queue driver, middleware state, and browser SDK state. If the queue driver is not `sync`, normal events are only sent while a queue worker is running:

```bash
php artisan queue:work -v
```

To trace normal event handling in the Laravel log:

```env
ED_SIGNAL_DEBUG=true
```

After changing `.env`, run `php artisan config:clear`. Then inspect `storage/logs/laravel.log` for queue dispatch, collector acceptance, retryable HTTP failures, and permanently failed jobs. For a quick worker-free check, temporarily set `ED_SIGNAL_DISPATCH_MODE=sync`.

## Notes

- Historical records are not backfilled.
- For SPA-heavy apps keep browser SDK enabled.
- If you use custom reverse proxy headers for country, configure them in `config/ed-signal.php`.
