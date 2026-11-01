<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs people in, and sends each of them to the door that is theirs.
 *
 * Two panels exist, one per role, and somebody will eventually type the other
 * one: a bookmark, a link somebody sent, or the address bar after signing out.
 * Filament's answer to that is a 403, which treats a mistyped address as an
 * attempt to reach something forbidden. This sends them to the panel that
 * belongs to them instead.
 *
 * It extends Filament's own Authenticate rather than being bolted in beside it,
 * and the placement is the whole reason. Two other arrangements were tried and
 * both fail for the same reason in opposite directions:
 *
 *  - As panel middleware, Filament injects its own Authenticate *ahead* of the
 *    panel's list, so the 403 is already sent before this runs.
 *  - As global middleware it does run first, but global middleware runs before
 *    StartSession, so `$request->user()` is null, this concludes there is nothing
 *    to redirect, and Filament's 403 goes out a moment later.
 *
 * Extending the middleware puts this check exactly where Filament's own is, which
 * is after the session is available and before anything is served.
 *
 * A disabled account can reach neither panel, and redirecting it to "its own"
 * would bounce it between them for ever, so it falls through to Filament and is
 * refused once.
 */
final class RedirectToOwnPanel extends Authenticate
{
    /**
     * The variadic $guards is the parent's own signature and must be reproduced
     * exactly. Leaving it off is a fatal declaration error rather than a warning,
     * and it takes down every panel page at once.
     */
    public function handle($request, Closure $next, ...$guards): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            $own = $user->isAdmin() ? 'admin' : 'student';

            if ($user->canAccessPanel(Filament::getPanel($own))) {
                return redirect()->route("filament.{$own}.pages.dashboard");
            }
        }

        return parent::handle($request, $next);
    }
}