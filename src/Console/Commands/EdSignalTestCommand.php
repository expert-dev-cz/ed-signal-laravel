<?php

namespace ExpertDev\EdSignalLaravel\Console\Commands;

use ExpertDev\EdSignalLaravel\Support\SignedServerEventClient;
use Illuminate\Console\Command;

class EdSignalTestCommand extends Command
{
    protected $signature = 'ed-signal:test';

    protected $description = 'Sends a signed ED Signal test event to verify adapter connectivity.';

    public function handle(SignedServerEventClient $client): int
    {
        $config = config('ed-signal');

        $payload = [
            'schema_version' => '1.0',
            'site_id' => (string) config('ed-signal.site_id', ''),
            'event_id' => 'laravel-test-' . time(),
            'event_name' => 'generate_lead',
            'event_time' => now()->toIso8601String(),
            'consent' => [
                'analytics' => true,
                'marketing' => true,
                'preferences' => false,
            ],
            'data' => [
                'adapter_test' => true,
                'cms' => 'laravel',
                'consent_mode' => 'with_consent',
            ],
        ];

        $client->send($config, $payload);

        $this->info('Test event sent. Check ED Signal ingest logs for acceptance.');

        return self::SUCCESS;
    }
}
