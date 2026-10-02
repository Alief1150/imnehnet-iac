<?php

namespace App\Filament\Pages;

use App\Support\EnvironmentWorkspace;
use App\Support\Lucide;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Deployment settings, read only.
 *
 * The specification asks for a settings page and says it can stay minimal, so
 * this one reports what the deployment is actually configured with rather than
 * offering switches. A switch that writes to .env would need a mechanism this
 * application deliberately does not have, and a page that looked editable while
 * silently doing nothing would be worse than one that plainly is not.
 *
 * Everything shown here is read from configuration, so it cannot drift from what
 * the application is actually doing.
 */
final class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static ?string $title = 'Settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 90;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function getNavigationIcon(): Htmlable|string|null
    {
        return Lucide::icon('activity');
    }

    /**
     * @return list<array{group: string, label: string, value: string, note: string}>
     */
    public function sections(): array
    {
        $browserAccess = (bool) config('infrastructure.terminal.browser_access_enabled');

        return [
            [
                'group' => 'Identity',
                'label' => 'Brand',
                'value' => 'IMNEHNET',
                'note' => 'Cybersecurity infrastructure control plane.',
            ],
            [
                'group' => 'Infrastructure',
                'label' => 'Incus remote',
                'value' => (string) config('infrastructure.incus_remote'),
                'note' => 'The compute plane the portal drives.',
            ],
            [
                'group' => 'Infrastructure',
                'label' => 'OpenTofu directory',
                'value' => (string) config('infrastructure.tofu.directory'),
                'note' => 'Every environment\'s state lives here, under one exclusive lock.',
            ],
            [
                'group' => 'Infrastructure',
                'label' => 'Ansible directory',
                'value' => (string) config('infrastructure.ansible.directory'),
                'note' => 'OpenTofu provisions; Ansible configures.',
            ],
            [
                'group' => 'Protection',
                'label' => 'Protected workspaces',
                'value' => EnvironmentWorkspace::protectedWorkspaces() === []
                    ? 'none'
                    : implode(', ', EnvironmentWorkspace::protectedWorkspaces()),
                'note' => 'Never selected by a portal-created environment.',
            ],
            [
                'group' => 'Protection',
                'label' => 'Protected containers',
                'value' => EnvironmentWorkspace::protectedContainers() === []
                    ? 'none'
                    : implode(', ', EnvironmentWorkspace::protectedContainers()),
                'note' => 'The reference environment. Readable, never operated on.',
            ],
            [
                'group' => 'Terminal',
                'label' => 'Browser access',
                'value' => $browserAccess ? 'ENABLED' : 'DISABLED',
                'note' => $browserAccess
                    ? 'The portal hands the browser an address for a live shell.'
                    : 'Sessions can start, but no address is given to a browser. '
                        .'Serving a shell over plain HTTP would send every keystroke across the network in the clear.',
            ],
            [
                'group' => 'Terminal',
                'label' => 'Session lifetime',
                'value' => config('infrastructure.terminal.session_ttl_minutes').' minutes',
                'note' => 'After this the process is killed, not merely forgotten.',
            ],
            [
                'group' => 'Terminal',
                'label' => 'Shell user',
                'value' => (string) config('infrastructure.terminal.shell_user'),
                'note' => 'Inside the container. A missing account refuses the session rather than falling back to root.',
            ],
            [
                'group' => 'Terminal',
                'label' => 'Concurrent sessions',
                'value' => (string) config('infrastructure.terminal.max_sessions_per_user'),
                'note' => 'Beyond this the oldest sessions are closed.',
            ],
        ];
    }
}
