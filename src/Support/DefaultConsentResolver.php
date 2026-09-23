<?php

namespace ExpertDev\EdSignalLaravel\Support;

use ExpertDev\EdSignalLaravel\Contracts\ConsentResolver;
use Illuminate\Http\Request;

class DefaultConsentResolver implements ConsentResolver
{
    public function __construct(
        private readonly array $config,
    ) {
    }

    public function resolve(Request $request): array
    {
        $default = [
            'analytics' => (bool) ($this->config['default']['analytics'] ?? false),
            'marketing' => (bool) ($this->config['default']['marketing'] ?? false),
            'preferences' => (bool) ($this->config['default']['preferences'] ?? false),
        ];

        $cookieName = (string) ($this->config['cookie_name'] ?? 'ed_consent_state');
        $raw = $request->cookie($cookieName);

        if (!is_string($raw) || trim($raw) === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $default;
        }

        return [
            'analytics' => array_key_exists('analytics', $decoded) ? (bool) $decoded['analytics'] : $default['analytics'],
            'marketing' => array_key_exists('marketing', $decoded) ? (bool) $decoded['marketing'] : $default['marketing'],
            'preferences' => array_key_exists('preferences', $decoded) ? (bool) $decoded['preferences'] : $default['preferences'],
        ];
    }

    public function receiptToken(Request $request): ?string
    {
        $cookieName = (string) ($this->config['receipt_cookie_name'] ?? 'ed_measurement_consent_receipt_token');
        $token = $request->cookie($cookieName);

        if (!is_string($token) || trim($token) === '') {
            return null;
        }

        return trim($token);
    }
}
