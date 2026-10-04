<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a signed-in user to the panel that belongs to them.
 *
 * Two panels exist, one per role, and somebody will eventually type the other
 * one: a bookmark, a link somebody sent, or the address bar after signing out.
 * A 403 answers that mistake with an accusation, so it is redirected instead.
 *
 * This is registered as global middleware rather than panel middleware, and that
 * is not a style choice. Filament inserts its own Authenticate in front of the
 * panel's middleware list, and that middleware aborts with 403 as soon as
 * canAccessPanel says no. As panel middleware this therefore always ran after the
 * refusal it was meant to prevent, which looked like it did nothing at all.
 * Global middleware runs before any route middleware, so it runs first.
 *
 * The panel is matched from the first path segment against the paths the panels
 * actually registered, because the current panel is not resolved until Filament's
 * own SetUpPanel middleware has run, which is later.
 *
 * A disabled account can reach neither panel, and following the redirect to "its
 * own" would bounce it between them for ever, so it is left to fail normally.
 */
final class RedirectToOwnPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $panel = $this->panelForPath($request->path());

        if ($panel === null || $user->canAccessPanel($panel)) {
            return $next($request);
        }

        $own = $user->isAdmin() ? 'admin' : 'student';

        if (! $user->canAccessPanel(Filament::getPanel($own))) {
            return $next($request);
        }

        return redirect()->route("filament.{$own}.pages.dashboard");
    }

    private function panelForPath(string $path): ?Panel
    {
        $segment = explode('/', trim($path, '/'))[0] ?? '';

        foreach (Filament::getPanels() as $panel) {
            if ($panel->getPath() === $segment) {
                return $panel;
            }
        }

        return null;
    }
}