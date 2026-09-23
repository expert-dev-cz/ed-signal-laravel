<?php

namespace ExpertDev\EdSignalLaravel\Jobs;

use ExpertDev\EdSignalLaravel\Support\SignedServerEventClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $client->send(config('ed-signal'), $this->payload);
    }
}
