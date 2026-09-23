<?php

namespace ExpertDev\EdSignalLaravel\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SignedServerEventClient
{
    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     * @return array{ok:bool,status_code:int,error_message:string,response_body:string,endpoint:string}
     */
    public function send(array $config, array $payload): array
    {
        $baseUrl = rtrim((string) ($config['collector_base_url'] ?? ''), '/');
        $siteId = (string) ($config['site_id'] ?? '');
        $keyId = (string) ($config['key_id'] ?? '');
        $secret = (string) ($config['secret'] ?? '');
        $endpoint = $baseUrl !== '' ? $baseUrl . '/v1/server-events' : '';

        if ($baseUrl === '' || $siteId === '' || $keyId === '' || $secret === '') {
            return [
                'ok' => false,
                'status_code' => 0,
                'error_message' => 'Missing ED Signal configuration values (collector_base_url/site_id/key_id/secret).',
                'response_body' => '',
                'endpoint' => $endpoint,
            ];
        }

        $timestamp = (string) time();
        $requestId = (string) Str::uuid();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if (!is_string($body) || $body === '') {
            return [
                'ok' => false,
                'status_code' => 0,
                'error_message' => 'Failed to encode request payload.',
                'response_body' => '',
                'endpoint' => $endpoint,
            ];
        }

        $signature = $this->createSignature($siteId, $keyId, $timestamp, $requestId, $body, $secret);

        try {
            $response = Http::timeout(max(2, (int) ($config['request_timeout'] ?? 10)))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Site-ID' => $siteId,
                    'X-Key-ID' => $keyId,
                    'X-Timestamp' => $timestamp,
                    'X-Request-ID' => $requestId,
                    'X-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint);

            return [
                'ok' => $response->successful(),
                'status_code' => $response->status(),
                'error_message' => $response->successful() ? '' : ('Request failed with HTTP ' . $response->status() . '.'),
                'response_body' => (string) $response->body(),
                'endpoint' => $endpoint,
            ];
        } catch (ConnectionException $exception) {
            return [
                'ok' => false,
                'status_code' => 0,
                'error_message' => $exception->getMessage(),
                'response_body' => '',
                'endpoint' => $endpoint,
            ];
        }
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
