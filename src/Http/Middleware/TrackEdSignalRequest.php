<?php

namespace ExpertDev\EdSignalLaravel\Http\Middleware;

use Closure;
use ExpertDev\EdSignalLaravel\Contracts\ConsentResolver;
use ExpertDev\EdSignalLaravel\EdSignal;
use ExpertDev\EdSignalLaravel\Support\SdkInjector;
use ExpertDev\EdSignalLaravel\Support\VisitorSessionManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackEdSignalRequest
{
    public function __construct(
        private readonly VisitorSessionManager $visitorSessionManager,
        private readonly ConsentResolver $consentResolver,
        private readonly EdSignal $edSignal,
        private readonly SdkInjector $sdkInjector,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $excluded = $this->isExcluded($request);
        if (!config('ed-signal.enabled', false) || $excluded) {
            /** @var Response $response */
            $response = $next($request);
            $this->addDebugHeader($response, $excluded ? 'excluded' : 'disabled');
            return $response;
        }

        $session = $this->visitorSessionManager->resolve($request, (array) config('ed-signal.cookies', []));
        $consent = $this->consentResolver->resolve($request);

        $request->attributes->set('ed_signal.session', $session);
        $request->attributes->set('ed_signal.consent', $consent);

        if ($this->shouldTrackServerPageView($request)) {
            $traffic = $this->resolveTrafficContext($request);

            $this->edSignal->event('page_view', [
                'path' => '/' . ltrim($request->path(), '/'),
                'traffic_channel' => $traffic['channel'],
                'traffic_source' => $traffic['source'],
                'traffic_medium' => $traffic['medium'],
                'traffic_campaign' => $traffic['campaign'],
                'traffic_is_direct' => $traffic['is_direct'],
                'traffic_referrer_host' => $traffic['referrer_host'],
            ], [
                'request' => $request,
                'session' => $session,
                'consent' => $consent,
            ]);
        }

        if ($this->shouldTrackServerFormSubmit($request)) {
            $source = $this->isLivewireRequest($request) ? 'livewire_ajax' : 'laravel_post';

            $this->edSignal->event('form_submit', [
                'path' => '/' . ltrim($request->path(), '/'),
                'safe_field_count' => count($this->safePostFields($request)),
                'source' => $source,
            ], [
                'request' => $request,
                'session' => $session,
                'consent' => $consent,
            ]);
        }

        /** @var Response $response */
        $response = $next($request);

        foreach ($session['cookies'] as $cookie) {
            $response->headers->setCookie($cookie);
        }

        if ($this->shouldInjectSdk($response)) {
            $content = (string) $response->getContent();
            $init = [
                'siteId' => (string) config('ed-signal.site_id', ''),
                'endpoint' => rtrim((string) config('ed-signal.collector_base_url', ''), '/') . '/v1/events',
                'consentEndpoint' => rtrim((string) config('ed-signal.collector_base_url', ''), '/') . '/v1/consent/receipts',
                'consent' => $consent,
                'consentReceiptToken' => $this->consentResolver->receiptToken($request),
                'consentSource' => (string) config('ed-signal.browser.consent_source', 'laravel_adapter'),
            ];

            $sdkUrl = (string) config('ed-signal.browser.sdk_url', '');
            $response->setContent($this->sdkInjector->inject($content, $sdkUrl, $init));
        }

        $this->addDebugHeader($response, 'active');

        return $response;
    }

    private function addDebugHeader(Response $response, string $status): void
    {
        if ((bool) config('ed-signal.debug', false)) {
            $response->headers->set('X-ED-Signal-Debug', $status);
        }
    }

    private function shouldInjectSdk(Response $response): bool
    {
        if (!config('ed-signal.browser.enabled', true) || !config('ed-signal.browser.inject_on_html', true)) {
            return false;
        }

        if ((string) config('ed-signal.browser.mode', 'middleware') !== 'middleware') {
            return false;
        }

        if ((string) config('ed-signal.browser.sdk_url', '') === '') {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('content-type', ''));

        return str_contains($contentType, 'text/html');
    }

    private function shouldTrackServerPageView(Request $request): bool
    {
        if (!config('ed-signal.tracking.track_page_view_server', true)) {
            return false;
        }

        if (!in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'], true)) {
            return false;
        }

        if ($request->expectsJson() || $request->isXmlHttpRequest()) {
            return false;
        }

        return true;
    }

    private function shouldTrackServerFormSubmit(Request $request): bool
    {
        if (!config('ed-signal.tracking.track_form_submit_server', true)) {
            return false;
        }

        if (strtoupper($request->getMethod()) !== 'POST') {
            return false;
        }

        if ($this->isLivewireRequest($request) && config('ed-signal.tracking.track_livewire_forms_server', true)) {
            return true;
        }

        if ($request->expectsJson() || $request->isXmlHttpRequest()) {
            return false;
        }

        return true;
    }

    /** @return array<string, scalar|null> */
    private function safePostFields(Request $request): array
    {
        $data = [];
        foreach ($request->request->all() as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $lower = strtolower($key);
            if (str_contains($lower, 'password') || str_contains($lower, 'token') || str_contains($lower, 'csrf')) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    private function isExcluded(Request $request): bool
    {
        if ($this->isLivewireRequest($request) && config('ed-signal.tracking.track_livewire_forms_server', true)) {
            return false;
        }

        $patterns = (array) config('ed-signal.tracking.exclude_paths', []);

        foreach ($patterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && $request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isLivewireRequest(Request $request): bool
    {
        if ($request->is('livewire/*')) {
            return true;
        }

        if ($request->headers->has('X-Livewire')) {
            return true;
        }

        return is_array($request->input('components')) || is_array($request->input('fingerprint'));
    }

    /** @return array{channel:string,source:string,medium:string,campaign:?string,is_direct:bool,referrer_host:?string} */
    private function resolveTrafficContext(Request $request): array
    {
        $utmSource = $this->cleanLower((string) $request->query('utm_source', ''));
        $utmMedium = $this->cleanLower((string) $request->query('utm_medium', ''));
        $utmCampaign = $this->clean((string) $request->query('utm_campaign', ''));
        $gclid = $this->clean((string) $request->query('gclid', ''));
        $gbraid = $this->clean((string) $request->query('gbraid', ''));
        $wbraid = $this->clean((string) $request->query('wbraid', ''));

        $paidSignal = $gclid !== null || $gbraid !== null || $wbraid !== null;

        $referrer = (string) $request->headers->get('referer', '');
        $referrerHost = $this->extractHost($referrer);

        $source = $utmSource ?? ($referrerHost ?? 'direct');
        $medium = $utmMedium ?? ($paidSignal ? 'cpc' : ($referrerHost !== null ? 'referral' : '(none)'));

        $channel = 'other';
        if ($source === 'direct' && $medium === '(none)' && !$paidSignal) {
            $channel = 'direct';
        } elseif ($paidSignal || in_array($medium, ['cpc', 'ppc', 'paid', 'paid_search', 'sem'], true)) {
            $channel = 'paid_search';
        } elseif ($medium === 'organic' || $this->isSearchHost($source)) {
            $channel = 'organic_search';
        } elseif (in_array($medium, ['social', 'paid_social', 'social_paid'], true) || $this->isSocialHost($source)) {
            $channel = 'social';
        } elseif ($medium === 'email') {
            $channel = 'email';
        } elseif ($medium === 'referral') {
            $channel = 'referral';
        }

        return [
            'channel' => $channel,
            'source' => $source,
            'medium' => $medium,
            'campaign' => $utmCampaign,
            'is_direct' => $channel === 'direct',
            'referrer_host' => $referrerHost,
        ];
    }

    private function clean(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function cleanLower(string $value): ?string
    {
        $clean = $this->clean($value);

        return $clean === null ? null : strtolower($clean);
    }

    private function extractHost(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        return strtolower($host);
    }

    private function isSearchHost(string $source): bool
    {
        return str_contains($source, 'google.')
            || str_contains($source, 'bing.com')
            || str_contains($source, 'duckduckgo.com')
            || str_contains($source, 'seznam.cz')
            || str_contains($source, 'yahoo.');
    }

    private function isSocialHost(string $source): bool
    {
        return str_contains($source, 'facebook.com')
            || str_contains($source, 'instagram.com')
            || str_contains($source, 'linkedin.com')
            || str_contains($source, 'tiktok.com')
            || str_contains($source, 'x.com')
            || str_contains($source, 'twitter.com')
            || str_contains($source, 'youtube.com');
    }
}
