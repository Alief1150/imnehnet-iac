<?php

namespace App\Services\GuidedCtf;

use App\Infrastructure\Health\CommandRunner;
use RuntimeException;

final class ContainerAbsenceVerifier
{
    public function __construct(
        private readonly CommandRunner $commands,
    ) {}

    /**
     * Fail closed: return true only when a successful Incus inventory
     * explicitly confirms the exact container name is absent.
     */
    public function isAbsent(string $remote, string $container): bool
    {
        $remote = rtrim($remote, ':');

        if (
            ! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/D', $remote)
            || ! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/D', $container)
        ) {
            throw new RuntimeException('Invalid Incus remote or container identifier.');
        }

        $result = $this->commands->run(
            ['incus', 'list', $remote.':', '--format=json'],
            20,
        );

        if (($result['successful'] ?? false) !== true) {
            throw new RuntimeException(
                'Cannot verify container absence: Incus inventory unavailable.'
            );
        }

        try {
            $instances = json_decode(
                $result['stdout'] ?? '',
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            throw new RuntimeException(
                'Cannot verify container absence: invalid Incus inventory.'
            );
        }

        if (! is_array($instances) || ! array_is_list($instances)) {
            throw new RuntimeException(
                'Cannot verify container absence: unexpected inventory structure.'
            );
        }

        foreach ($instances as $instance) {
            if (
                ! is_array($instance)
                || ! isset($instance['name'])
                || ! is_string($instance['name'])
            ) {
                throw new RuntimeException(
                    'Cannot verify container absence: incomplete inventory.'
                );
            }

            if ($instance['name'] === $container) {
                return false;
            }
        }

        return true;
    }
}
