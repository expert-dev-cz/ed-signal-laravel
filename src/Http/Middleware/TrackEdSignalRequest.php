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
        if (!config('ed-signal.enabled', false) || $this->isExcluded($request)) {
            /** @var Response $response */
            $response = $next($request);
            return $response;
        }

        $session = $this->visitorSessionManager->resolve($request, (array) config('ed-signal.cookies', []));
        $consent = $this->consentResolver->resolve($request);

        $request->attributes->set('ed_signal.session', $session);
        $request->attributes->set('ed_signal.consent', $consent);

        if ($this->shouldTrackServerPageView($request)) {
            $this->edSignal->event('page_view', [
                'path' => '/' . ltrim($request->path(), '/'),
                'traffic_source' => $request->query('utm_source', 'direct'),
                'traffic_medium' => $request->query('utm_medium', '(none)'),
                'traffic_campaign' => $request->query('utm_campaign'),
            ], [
                'request' => $request,
                'session' => $session,
                'consent' => $consent,
            ]);
        }

        if ($this->shouldTrackServerFormSubmit($request)) {
            $this->edSignal->event('form_submit', [
                'path' => '/' . ltrim($request->path(), '/'),
                'safe_field_count' => count($this->safePostFields($request)),
                'source' => 'laravel_post',
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

        return $response;
    }

    private function shouldInjectSdk(Response $response): bool
    {
        if (!config('ed-signal.browser.enabled', true) || !config('ed-signal.browser.inject_on_html', true)) {
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
        $patterns = (array) config('ed-signal.tracking.exclude_paths', []);

        foreach ($patterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && $request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
