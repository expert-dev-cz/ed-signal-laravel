<?php

namespace ExpertDev\EdSignalLaravel;

use ExpertDev\EdSignalLaravel\Contracts\ConsentResolver;
use ExpertDev\EdSignalLaravel\Jobs\SendServerEvent;
use ExpertDev\EdSignalLaravel\Support\CountryResolver;
use ExpertDev\EdSignalLaravel\Support\DataSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class EdSignal
{
    public function __construct(
        private readonly ConsentResolver $consentResolver,
        private readonly CountryResolver $countryResolver,
        private readonly DataSanitizer $dataSanitizer,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    public function event(string $eventName, array $data = [], array $context = []): void
    {
        if (!config('ed-signal.enabled', false) || !config('ed-signal.send_server_events', true)) {
            return;
        }

        $request = $context['request'] ?? request();
        if (!$request instanceof Request) {
            return;
        }

        $session = $context['session'] ?? [];
        $consent = $context['consent'] ?? $this->consentResolver->resolve($request);
        $payload = $this->buildPayload($request, $eventName, $data, $session, $consent);

        $queue = config('ed-signal.queue');
        $connection = config('ed-signal.queue_connection');

        $job = new SendServerEvent($payload);

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        if (is_string($connection) && $connection !== '') {
            $job->onConnection($connection);
        }

        dispatch($job);
    }

    /**
     * @param array<string, mixed> $items
     * @param array<string, mixed> $data
     */
    public function purchase(string $transactionId, float $value, string $currency, array $items = [], array $data = [], array $context = []): void
    {
        $ecommerce = [
            'transaction_id' => $transactionId,
            'currency' => $currency,
            'value' => round($value, 2),
            'items' => $items,
        ];

        $this->event('purchase', array_merge($data, ['ecommerce' => $ecommerce]), $context);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $session
     * @param array<string, bool> $consent
     * @return array<string, mixed>
     */
    private function buildPayload(Request $request, string $eventName, array $data, array $session, array $consent): array
    {
        $siteId = (string) config('ed-signal.site_id', '');
        $safeData = $this->dataSanitizer->sanitize($data, (array) config('ed-signal.privacy.blocked_key_fragments', []));

        $country = $this->countryResolver->resolve($request);
        if ($country !== null) {
            $safeData['approx_country'] = $country;
        }

        $safeData['consent_mode'] = $this->resolveConsentMode($consent);

        $ecommerce = Arr::get($safeData, 'ecommerce', []);
        if (is_array($ecommerce)) {
            unset($safeData['ecommerce']);
        } else {
            $ecommerce = [];
        }

        return [
            'schema_version' => '1.0',
            'site_id' => $siteId,
            'event_id' => Str::lower($eventName) . '-' . Str::uuid()->toString(),
            'event_name' => Str::lower($eventName),
            'event_time' => now()->toIso8601String(),
            'session' => [
                'client_id' => (string) ($session['client_id'] ?? ''),
                'session_id' => (string) ($session['session_id'] ?? ''),
            ],
            'consent' => [
                'analytics' => (bool) ($consent['analytics'] ?? false),
                'marketing' => (bool) ($consent['marketing'] ?? false),
                'preferences' => (bool) ($consent['preferences'] ?? false),
            ],
            'consent_receipt' => $this->consentResolver->receiptToken($request),
            'page' => [
                'url' => $request->fullUrl(),
                'path' => '/' . ltrim($request->path(), '/'),
                'title' => null,
                'referrer' => $request->headers->get('referer'),
            ],
            'attribution' => $this->extractAttribution($request),
            'data' => $safeData,
            'ecommerce' => $ecommerce,
        ];
    }

    /** @return array<string, mixed> */
    private function extractAttribution(Request $request): array
    {
        $utmSource = $this->clean($request->query('utm_source'));
        $utmMedium = $this->clean($request->query('utm_medium'));
        $utmCampaign = $this->clean($request->query('utm_campaign'));
        $utmContent = $this->clean($request->query('utm_content'));
        $utmTerm = $this->clean($request->query('utm_term'));

        return array_filter([
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_content' => $utmContent,
            'utm_term' => $utmTerm,
            'gclid' => $this->clean($request->query('gclid')),
            'gbraid' => $this->clean($request->query('gbraid')),
            'wbraid' => $this->clean($request->query('wbraid')),
            'landing_page' => $request->fullUrl(),
            'referrer' => $this->clean($request->headers->get('referer')),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    private function clean(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, bool> $consent */
    private function resolveConsentMode(array $consent): string
    {
        if (!empty($consent['analytics']) || !empty($consent['marketing']) || !empty($consent['preferences'])) {
            return 'with_consent';
        }

        return 'without_consent';
    }
}
