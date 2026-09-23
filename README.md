# ED Signal Laravel Adapter

Reusable Laravel package for custom (non-WordPress) websites. It mirrors the WordPress adapter behavior: automatic browser SDK init, server-side event delivery, consent mode handling, attribution, anonymous visitor/session IDs, and anonymized country enrichment.

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
ED_SIGNAL_COLLECTOR_URL=https://tracking.example.com/api
ED_SIGNAL_SITE_ID=your_site_id
ED_SIGNAL_KEY_ID=your_key_id
ED_SIGNAL_SECRET=your_secret

ED_SIGNAL_BROWSER_SDK=true
ED_SIGNAL_SDK_URL=https://tracking.example.com/measurement/v1/measurement.js

ED_SIGNAL_QUEUE=default
ED_SIGNAL_REQUEST_TIMEOUT=10

# Same cookie bar across Laravel websites
ED_SIGNAL_CONSENT_COOKIE=ed_consent_state
ED_SIGNAL_CONSENT_RECEIPT_COOKIE=ed_measurement_consent_receipt_token
```

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

## Notes

- Historical records are not backfilled.
- For SPA-heavy apps keep browser SDK enabled.
- If you use custom reverse proxy headers for country, configure them in `config/ed-signal.php`.
