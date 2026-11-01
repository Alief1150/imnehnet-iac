<?php

namespace App\Support;

use DomainException;

/**
 * Derivation and protection rules for OpenTofu workspaces.
 *
 * OpenTofu keeps every environment's state in one working directory, and the
 * directory only remembers which workspace is currently selected. Two things
 * follow from that, and both are guarded here rather than at the call site,
 * because a mistake here destroys somebody's infrastructure.
 *
 * First, a workspace has to be selected before every command, so two lifecycle
 * jobs running at once would interleave "select workspace A" with "select
 * workspace B" and apply against the wrong environment. Callers must hold an
 * exclusive lock for the whole transaction; this class only derives the name.
 *
 * Second, the reference environment and the default workspace are off limits.
 * The default workspace holds the state of the reference container, so a stray
 * destroy aimed at "default" would take down a container that exists purely as
 * a protected baseline.
 */
final class EnvironmentWorkspace
{
    /**
     * Matches the identifier rule from the specification. Deliberately strict:
     * the name ends up in a shell command, a workspace name and a URL, so
     * anything a shell could interpret is rejected outright rather than escaped.
     */
    public const NAME_PATTERN = '/^[a-z0-9][a-z0-9-]{2,62}$/';

    public static function isValidContainerName(string $name): bool
    {
        return (bool) preg_match(self::NAME_PATTERN, $name);
    }

    /**
     * One workspace per environment, named so the mapping is reversible: given
     * a workspace name the container can be recovered without a lookup table,
     * which is what makes read-only reconciliation possible.
     */
    public static function forContainer(string $containerName): string
    {
        self::assertValidContainerName($containerName);

        return 'env-'.$containerName;
    }

    public static function assertValidContainerName(string $name): void
    {
        if (! self::isValidContainerName($name)) {
            throw new DomainException(sprintf(
                'The container name must be 3 to 63 characters of lowercase letters, digits and dashes, and must not start with a dash. "%s" is not acceptable.',
                mb_substr($name, 0, 64)
            ));
        }
    }

    /**
     * @throws DomainException when the workspace is the protected default
     */
    public static function assertUsable(string $workspace, string $containerName): void
    {
        if (in_array($workspace, self::protectedWorkspaces(), true)) {
            throw new DomainException(sprintf(
                'The "%s" workspace is reserved for the reference environment and cannot be used by the portal.',
                $workspace
            ));
        }

        if (in_array($containerName, self::protectedContainers(), true)) {
            throw new DomainException(sprintf(
                '"%s" is the protected reference environment. It is never created, changed or destroyed by the portal.',
                $containerName
            ));
        }
    }

    /**
     * True when the pair is safe to operate on. Used by guards that should
     * hide an action rather than throw when it is clicked.
     */
    public static function isProtected(string $workspace, string $containerName): bool
    {
        return in_array($workspace, self::protectedWorkspaces(), true)
            || in_array($containerName, self::protectedContainers(), true);
    }

    /**
     * @return list<string>
     */
    public static function protectedWorkspaces(): array
    {
        return (array) config('infrastructure.protected_workspaces', ['default']);
    }

    /**
     * @return list<string>
     */
    public static function protectedContainers(): array
    {
        return (array) config('infrastructure.protected_containers', ['lab-tofu-test']);
    }
}
