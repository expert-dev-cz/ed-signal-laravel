<?php

namespace ExpertDev\EdSignalLaravel\Console\Commands;

use ExpertDev\EdSignalLaravel\Support\SignedServerEventClient;
use Illuminate\Console\Command;

class EdSignalTestCommand extends Command
{
    protected $signature = 'ed-signal:test {--vv : Show response body and config diagnostics}';

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

        $result = $client->send($config, $payload);

        $this->line('Endpoint: ' . ($result['endpoint'] !== '' ? $result['endpoint'] : '(missing)'));

        if ($result['ok']) {
            $this->info('Test event accepted by collector (HTTP ' . $result['status_code'] . ').');

            if ($this->option('vv') && $result['response_body'] !== '') {
                $this->newLine();
                $this->line('Response body:');
                $this->line($result['response_body']);
            }

            return self::SUCCESS;
        }

        $this->error('Test event failed. ' . $result['error_message']);
        $this->line('HTTP status: ' . $result['status_code']);

        if ($result['response_body'] !== '') {
            $this->newLine();
            $this->line('Response body:');
            $this->line($result['response_body']);
        }

        if ($this->option('vv')) {
            $this->newLine();
            $this->line('Config diagnostics:');
            $this->line('ED_SIGNAL_COLLECTOR_URL: ' . ((string) config('ed-signal.collector_base_url') !== '' ? 'set' : 'missing'));
            $this->line('ED_SIGNAL_SITE_ID: ' . ((string) config('ed-signal.site_id') !== '' ? 'set' : 'missing'));
            $this->line('ED_SIGNAL_KEY_ID: ' . ((string) config('ed-signal.key_id') !== '' ? 'set' : 'missing'));
            $this->line('ED_SIGNAL_SECRET: ' . ((string) config('ed-signal.secret') !== '' ? 'set' : 'missing'));
        }

        return self::FAILURE;
    }
}
