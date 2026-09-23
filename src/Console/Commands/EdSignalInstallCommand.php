<?php

namespace ExpertDev\EdSignalLaravel\Console\Commands;

use Illuminate\Console\Command;

class EdSignalInstallCommand extends Command
{
    protected $signature = 'ed-signal:install';

    protected $description = 'Publishes ED Signal config and prints quick setup steps.';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'ed-signal-config']);

        $this->newLine();
        $this->info('ED Signal config published to config/ed-signal.php');
        $this->line('Next: set ED_SIGNAL_COLLECTOR_URL, ED_SIGNAL_SITE_ID, ED_SIGNAL_KEY_ID, ED_SIGNAL_SECRET in .env');
        $this->line('Then run: php artisan ed-signal:test');

        return self::SUCCESS;
    }
}
