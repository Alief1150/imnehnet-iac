<?php

namespace App\Console\Commands;

use App\Services\SecurityTelemetryCollector;
use Illuminate\Console\Command;

class CollectSecurityTelemetryCommand extends Command
{
    protected $signature = 'security:collect';

    protected $description = 'Collect Wazuh security telemetry';

    public function handle(SecurityTelemetryCollector $collector): int
    {
        $telemetry = $collector->collect();

        $this->info(sprintf(
            'Security telemetry collected at %s; alerts=%s',
            date('c', $telemetry['collected_at'] ?? time()),
            ($telemetry['alerts']['ok'] ?? false) ? 'OK' : 'UNAVAILABLE'
        ));

        return self::SUCCESS;
    }
}
