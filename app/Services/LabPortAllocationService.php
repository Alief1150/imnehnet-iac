<?php

namespace App\Services;

use App\Models\Lab;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LabPortAllocationService
{
    /**
     * Published host ports are allocated from disjoint ranges so two students can
     * never be handed the same one. The container is always reached through a
     * published port, which is why the whole container subnet stays closed.
     */
    private const SSH_RANGE = [22001, 22099];

    private const WEB_RANGE = [28001, 28099];

    public function allocateSshPort(Lab $lab): int
    {
        return $this->allocate($lab, 'ssh_port', self::SSH_RANGE);
    }

    public function allocateWebPort(Lab $lab): int
    {
        return $this->allocate($lab, 'web_port', self::WEB_RANGE);
    }

    /**
     * @param  array{0: int, 1: int}  $range
     */
    private function allocate(Lab $lab, string $column, array $range): int
    {
        $existing = $lab->{$column};

        if (is_int($existing) || (is_string($existing) && $existing !== '')) {
            return (int) $existing;
        }

        return DB::transaction(function () use ($lab, $column, $range): int {
            $taken = Lab::query()
                ->whereNotNull($column)
                ->pluck($column)
                ->map(fn (mixed $value): int => (int) $value)
                ->all();

            for ($port = $range[0]; $port <= $range[1]; $port++) {
                if (! in_array($port, $taken, true)) {
                    $lab->forceFill([$column => $port])->saveQuietly();

                    return $port;
                }
            }

            throw new RuntimeException(sprintf('No free port remains in the %d-%d range.', $range[0], $range[1]));
        });
    }
}
