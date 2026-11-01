<?php

namespace App\Console\Commands;

use App\Services\Terminal\AdminTerminalService;
use App\Services\TerminalService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Close every terminal session whose window has passed.
 *
 * ── Why this exists ──────────────────────────────────────────────────────────────────
 *
 * Both terminal services state a rule that closing is not the only way out of a session —
 * a shell must not be able to outlive its lease. Neither service had anything that actually
 * ran that rule. TerminalService::reapExpired() and AdminTerminalService::reapExpired()
 * were both reachable only from a request, so a session whose browser tab was closed, or
 * whose operator walked away from the machine, stayed open until a php-fpm worker recycled
 * and the process was orphaned with nothing left holding its port.
 *
 * An unattended reaper is the backstop that makes the rule true rather than intended. This
 * is the only path in the codebase that closes a shell nobody asked to have closed, which is
 * why it reports what it did instead of running silently.
 *
 * ── The two reapers, and why both ────────────────────────────────────────────────────
 *
 * They are separate services on purpose — a student shell is confined to a container, an
 * administrative one reaches the host — and each only reaps its own kind of session. A
 * single reaper that did not distinguish would end a student's shell on the administrator's
 * schedule, and an administrator's shell on the student's shorter window.
 */
class ReapTerminalSessionsCommand extends Command
{
    protected $signature = 'terminal:reap
                            {--quiet-report : Print only the totals}';

    protected $description = 'Close terminal sessions whose lease has expired, and report how many';

    public function handle(AdminTerminalService $admin, TerminalService $student): int
    {
        $results = [];

        // Each reaper is isolated: one kind of session throwing must not leave the other
        // kind un-reaped, because a failure that stops the backstop is the failure this
        // command exists to prevent.
        foreach ([
            'student' => fn (): int => $student->reapExpired(),
            'administrative' => fn (): int => $admin->reapExpired(),
        ] as $label => $reap) {
            try {
                $results[$label] = $reap();
            } catch (Throwable $exception) {
                $results[$label] = null;

                $this->error(sprintf(
                    'Reaping %s sessions failed: %s',
                    $label,
                    $exception->getMessage(),
                ));

                report($exception);
            }
        }

        $total = array_sum(array_filter($results, is_int(...)));

        if (! $this->option('quiet-report')) {
            foreach ($results as $label => $count) {
                $this->line(sprintf(
                    '  %-14s %s',
                    $label,
                    $count === null ? 'FAILED — see the log above' : $count.' closed',
                ));
            }
        }

        $this->line(sprintf('  %-14s %d', 'total', $total));

        // A partial failure is worth a non-zero exit so a monitoring check notices, but the
        // command has still done the part it could: the other reaper ran.
        return in_array(null, $results, true) ? self::FAILURE : self::SUCCESS;
    }
}