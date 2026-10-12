<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Tailscale Serve connects to the Laravel development listener over loopback.
        // Trust HTTPS forwarding only from loopback, not arbitrary Tailscale peers.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Nothing global. RedirectToOwnPanel was tried here and could not work:
        // global middleware runs before StartSession, so the signed-in user is not
        // readable yet, the middleware concludes there is nobody to redirect, and
        // Filament's own 403 goes out a moment later. It is now the panels' auth
        // middleware instead.
    })
    ->withSchedule(function (Schedule $schedule): void {
          // The backstop that makes "a shell cannot outlive its lease" true rather than
          // intended. Without it, reapExpired() is only reachable from a request, so a
          // session whose tab was closed stays open until the process is orphaned with
          // nothing holding its port.
          //
          // Five minutes because the shortest lease is fifteen and nothing here is worth
          // more precision than that. withoutOverlapping because two runs racing would each
          // try to signal the same pids, and the second would find the first had already
          // done the work.
          $schedule->command('terminal:reap --quiet-report')
              ->everyFiveMinutes()
              ->withoutOverlapping();
      })
      ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
