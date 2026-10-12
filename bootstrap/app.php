<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use App\Services\Terminal\AdminTerminalService;

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

        // The embedded terminal's ticket cookies travel in plaintext.
        //
        // Apache's embed gate reads the cookie value as the ticket filename, so an
        // encrypted value names a file that does not exist and every frame is
        // refused — measured as a mounted iframe answering 403 with the ticket
        // leased and the cookie queued. The names are exact because the middleware
        // matches them exactly, and they are built by the service rather than
        // repeated here, so renaming the cookie in one place renames it in both.
        $middleware->encryptCookies(except: array_map(
            fn (int $port): string => AdminTerminalService::embedCookieName($port),
            range(
                (int) env('TERMINAL_PORT_RANGE_MIN', 38000),
                (int) env('TERMINAL_PORT_RANGE_MAX', 38007),
            ),
        ));
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
