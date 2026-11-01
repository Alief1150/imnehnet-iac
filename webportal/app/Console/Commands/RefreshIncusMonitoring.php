<?php

namespace App\Console\Commands;

use App\Services\IncusInventoryMonitoringService;
use Illuminate\Console\Command;

final class RefreshIncusMonitoring extends Command
{
    protected $signature = 'monitoring:refresh-incus';

    protected $description = 'Refresh read-only Incus inventory snapshot for Monitoring';

    public function handle(IncusInventoryMonitoringService $inventory): int
    {
        $result = $inventory->refresh();
        $this->line(sprintf('Incus inventory: %d/%d planes available, %d containers (%d running, %d stopped, %d other).',
            $result['available'], 2, $result['total'], $result['running'], $result['stopped'], $result['other']));

        return $result['available'] === 2 ? self::SUCCESS : self::FAILURE;
    }
}
