<?php

namespace ExpertDev\EdSignalLaravel\Support;

class BrowserSnippetRenderer
{
    public function render(): string
    {
        if (!config('ed-signal.enabled', false) || !config('ed-signal.browser.enabled', true)) {
            return '';
        }

        $siteId = (string) config('ed-signal.site_id', '');
        $collectorBaseUrl = rtrim((string) config('ed-signal.collector_base_url', ''), '/');
        $sdkUrl = (string) config('ed-signal.browser.sdk_url', '');

        if ($siteId === '' || $collectorBaseUrl === '' || $sdkUrl === '') {
            return '';
        }

        $consentCookieName = (string) config('ed-signal.consent.cookie_name', 'ed_consent_state');
        $receiptCookieName = (string) config('ed-signal.consent.receipt_cookie_name', 'ed_measurement_consent_receipt_token');
        $defaultConsent = (array) config('ed-signal.consent.default', [
            'analytics' => false,
            'marketing' => false,
            'preferences' => false,
        ]);

        $init = [
            'siteId' => $siteId,
            'endpoint' => $collectorBaseUrl . '/v1/events',
            'consentEndpoint' => $collectorBaseUrl . '/v1/consent/receipts',
            'consent' => [
                'analytics' => (bool) ($defaultConsent['analytics'] ?? false),
                'marketing' => (bool) ($defaultConsent['marketing'] ?? false),
                'preferences' => (bool) ($defaultConsent['preferences'] ?? false),
            ],
            'consentSource' => (string) config('ed-signal.browser.consent_source', 'laravel_adapter'),
        ];

        $initJson = json_encode($init, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($initJson)) {
            return '';
        }

        $safeSdkUrl = htmlspecialchars($sdkUrl, ENT_QUOTES, 'UTF-8');
        $safeConsentCookie = json_encode($consentCookieName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '"ed_consent_state"';
        $safeReceiptCookie = json_encode($receiptCookieName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '"ed_measurement_consent_receipt_token"';

        return '<script>(function(){'
            . 'function readCookie(name){var m=document.cookie.match(new RegExp("(?:^|; )"+name.replace(/[.$?*|{}()\\[\\]\\\\/+^]/g,"\\\\$&")+"=([^;]*)"));return m?m[1]:null;}'
            . 'var consentRaw=readCookie(' . $safeConsentCookie . ');'
            . 'if(consentRaw){try{var parsed=JSON.parse(decodeURIComponent(consentRaw));if(parsed&&typeof parsed==="object"){window.__edConsent=parsed;}}catch(e){}}'
            . 'var receipt=readCookie(' . $safeReceiptCookie . ');'
            . 'if(receipt){try{window.__edConsentReceiptToken=decodeURIComponent(receipt);}catch(e){window.__edConsentReceiptToken=receipt;}}'
            . '})();</script>'
            . '<script async src="' . $safeSdkUrl . '" data-ed-signal-measurement-sdk="1"></script>'
            . '<script>window.ExpertMeasurement&&window.ExpertMeasurement.init(' . $initJson . ');</script>';
    }
}
