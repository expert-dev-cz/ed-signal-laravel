<?php

namespace ExpertDev\EdSignalLaravel\Support;

use Illuminate\Support\Facades\Http;

class SignedServerEventClient
{
    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     */
    public function send(array $config, array $payload): void
    {
        $baseUrl = rtrim((string) ($config['collector_base_url'] ?? ''), '/');
        $siteId = (string) ($config['site_id'] ?? '');
        $keyId = (string) ($config['key_id'] ?? '');
        $secret = (string) ($config['secret'] ?? '');

        if ($baseUrl === '' || $siteId === '' || $keyId === '' || $secret === '') {
            return;
        }

        $timestamp = (string) time();
        $requestId = (string) \Illuminate\Support\Str::uuid();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if (!is_string($body) || $body === '') {
            return;
        }

        $signature = $this->createSignature($siteId, $keyId, $timestamp, $requestId, $body, $secret);

        Http::timeout(max(2, (int) ($config['request_timeout'] ?? 10)))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Site-ID' => $siteId,
                'X-Key-ID' => $keyId,
                'X-Timestamp' => $timestamp,
                'X-Request-ID' => $requestId,
                'X-Signature' => $signature,
            ])
            ->withBody($body, 'application/json')
            ->post($baseUrl . '/v1/server-events');
    }

    private function createSignature(string $siteId, string $keyId, string $timestamp, string $requestId, string $rawBody, string $secret): string
    {
        $canonical = implode("\n", [
            'v1',
            $siteId,
            $keyId,
            $timestamp,
            $requestId,
            hash('sha256', $rawBody),
        ]);

        return base64_encode(hash_hmac('sha256', $canonical, $secret, true));
    }
}
