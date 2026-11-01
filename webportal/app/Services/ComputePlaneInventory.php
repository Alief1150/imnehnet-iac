<?php

namespace App\Services;

use App\Infrastructure\Health\CommandRunner;
use App\Models\LabEnvironment;
use Illuminate\Support\Facades\Cache;

/**
 * What is actually running on the compute plane, as opposed to what the portal
 * believes about it.
 *
 * Every other part of the portal asks the database what exists, because the
 * database is what it manages. This is the one place that asks Incus instead,
 * and the gap between the two answers is where the interesting failures live:
 * a container with no record is running something nobody owns, and a record with
 * no container is claiming something that is not there. Neither is visible from
 * the portal alone, so an operator has to go and look by hand.
 *
 * Reachability is reported rather than assumed. When Incus cannot be reached
 * this returns no names and says so, because reporting zero containers during an
 * outage would look exactly like a clean plane and would be a lie.
 */
final class ComputePlaneInventory
{
    public function __construct(
        private readonly CommandRunner $commands,
        private readonly string $remote = 'lab-env:',
    ) {}

    /**
     * Container names on the compute plane, and whether that list is trustworthy.
     *
     * @return array{names: list<string>, reachable: bool}
     */
    public function containers(): array
    {
        // Cached, because this runs on every dashboard load and the Incus health
        // probe on the same page already shells out to the same daemon.
        //
        // What is cached is this array of plain strings, not an object. Caching an
        // object means storing it serialized, and a cached value whose class has
        // since been renamed comes back as __PHP_Incomplete_Class and takes the
        // page down with a type error. A cache of strings cannot fail that way.
        return Cache::remember(
            'infrastructure.compute_plane.containers',
            now()->addSeconds($this->cacheSeconds()),
            function (): array {
                $result = $this->commands->run(['incus', 'list', $this->remote, '--format=json'], 5);

                if (! $result['successful']) {
                    return ['names' => [], 'reachable' => false];
                }

                $decoded = json_decode($result['stdout'], true);

                if (! is_array($decoded)) {
                    return ['names' => [], 'reachable' => false];
                }

                $names = [];

                foreach ($decoded as $instance) {
                    if (is_array($instance) && is_string($instance['name'] ?? null) && $instance['name'] !== '') {
                        $names[] = $instance['name'];
                    }
                }

                sort($names);

                return ['names' => $names, 'reachable' => true];
            },
        );
    }

    private function cacheSeconds(): int
    {
        return max(1, (int) config('infrastructure.resource_cache_seconds', 10));
    }

    /**
     * Containers that exist on the compute plane but belong to no record.
     *
     * The protected reference environment is excluded: it exists to be observed,
     * and it deliberately has no portal record to own it, so counting it here
     * would report a permanent false alarm.
     *
     * @return array{names: list<string>, reachable: bool}
     */
    public function unmanaged(): array
    {
        $containers = $this->containers();

        if (! $containers['reachable']) {
            return $containers;
        }

        $known = LabEnvironment::query()
            ->pluck('container_name')
            ->map(fn (string $name): string => $name)
            ->all();

        $protected = array_map(
            strval(...),
            (array) config('infrastructure.protected_containers', []),
        );

        $unmanaged = array_values(array_diff($containers['names'], $known, $protected));

        return ['names' => $unmanaged, 'reachable' => true];
    }
}