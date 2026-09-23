<?php

namespace ExpertDev\EdSignalLaravel\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class VisitorSessionManager
{
    /** @return array{client_id:string,session_id:string,cookies:list<Cookie>} */
    public function resolve(Request $request, array $cookieConfig): array
    {
        $visitorCookie = (string) ($cookieConfig['visitor_cookie'] ?? 'ed_signal_vid');
        $sessionCookie = (string) ($cookieConfig['session_cookie'] ?? 'ed_signal_sid');
        $sessionTsCookie = (string) ($cookieConfig['session_ts_cookie'] ?? 'ed_signal_sts');
        $sessionIdleSeconds = (int) ($cookieConfig['session_idle_seconds'] ?? 1800);

        $visitorValue = $this->readHexCookie($request, $visitorCookie, 32) ?? bin2hex(random_bytes(16));

        $now = time();
        $sessionValue = $this->readHexCookie($request, $sessionCookie, 32);
        $sessionTs = (int) $request->cookies->get($sessionTsCookie, 0);

        if (!is_string($sessionValue) || $sessionTs <= 0 || ($now - $sessionTs) > $sessionIdleSeconds) {
            $sessionValue = bin2hex(random_bytes(16));
        }

        $cookies = [
            $this->makeCookie($visitorCookie, $visitorValue, (int) ($cookieConfig['visitor_ttl_minutes'] ?? 568800), $cookieConfig),
            $this->makeCookie($sessionCookie, $sessionValue, (int) ($cookieConfig['session_ttl_minutes'] ?? 1440), $cookieConfig),
            $this->makeCookie($sessionTsCookie, (string) $now, (int) ($cookieConfig['session_ttl_minutes'] ?? 1440), $cookieConfig),
        ];

        return [
            'client_id' => 'lv_' . $visitorValue,
            'session_id' => 'ls_' . $sessionValue,
            'cookies' => $cookies,
        ];
    }

    private function readHexCookie(Request $request, string $cookieName, int $length): ?string
    {
        $value = $request->cookies->get($cookieName);

        if (!is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        if ($value === '' || strlen($value) !== $length) {
            return null;
        }

        if (preg_match('/^[a-f0-9]+$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function makeCookie(string $name, string $value, int $ttlMinutes, array $cookieConfig): Cookie
    {
        $secure = array_key_exists('secure', $cookieConfig) && $cookieConfig['secure'] !== null
            ? (bool) $cookieConfig['secure']
            : null;

        $sameSite = (string) ($cookieConfig['same_site'] ?? 'Lax');

        return cookie(
            name: $name,
            value: $value,
            minutes: max(1, $ttlMinutes),
            path: '/',
            domain: null,
            secure: $secure,
            httpOnly: false,
            raw: false,
            sameSite: $sameSite,
        );
    }
}
