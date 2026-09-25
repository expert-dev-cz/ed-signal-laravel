<?php

namespace ExpertDev\EdSignalLaravel\Jobs;

use ExpertDev\EdSignalLaravel\Support\SignedServerEventClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendServerEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public array $payload,
    ) {
    }

    public int $tries = 5;

    public function backoff(): array
    {
        return [60, 300, 1800, 7200, 21600];
    }

    public function handle(SignedServerEventClient $client): void
    {
        $result = $client->send((array) config('ed-signal'), $this->payload);

        if (!$result['ok']) {
            Log::warning('ED Signal queued event was not accepted by collector.', [
                'event_name' => $this->payload['event_name'] ?? null,
                'event_id' => $this->payload['event_id'] ?? null,
                'status_code' => $result['status_code'],
                'endpoint' => $result['endpoint'],
                'error' => $result['error_message'],
                'response_body' => $result['response_body'],
            ]);

            throw new RuntimeException(
                $result['error_message'] !== '' ? $result['error_message'] : 'ED Signal queued event send failed.'
            );
        }

        if ((bool) config('ed-signal.debug', false)) {
            Log::debug('ED Signal queued event accepted by collector.', [
                'event_name' => $this->payload['event_name'] ?? null,
                'event_id' => $this->payload['event_id'] ?? null,
                'status_code' => $result['status_code'],
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ED Signal queued event failed permanently.', [
            'event_name' => $this->payload['event_name'] ?? null,
            'event_id' => $this->payload['event_id'] ?? null,
            'error' => $exception?->getMessage(),
        ]);
    }
}
