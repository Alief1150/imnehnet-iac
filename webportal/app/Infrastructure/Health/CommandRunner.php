<?php

namespace App\Infrastructure\Health;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs a command as an argument vector and returns what it said.
 *
 * Every caller passes an array. That is the whole point of the class: a command built
 * by joining a string is a command in which a container name or a file path becomes
 * part of the syntax, and the two places that build such commands are the two places
 * where a name that came from a request would matter.
 */
class CommandRunner
{
    /**
     * @param  list<string>  $command
     * @param  string|null  $input  Written to the process's stdin, for a command that
     *                              takes content rather than a filename.
     * @return array{successful: bool, stdout: string, stderr: string}
     */
    public function run(
        array $command,
        int $timeoutSeconds = 3,
        ?string $workingDirectory = null,
        ?string $input = null,
    ): array {
        try {
            // The working directory is part of what makes a command meaningful:
            // running tofu in the wrong directory reports a healthy client while
            // reading the wrong state. Optional so existing callers are unchanged.
            $process = new Process($command, $workingDirectory, env: [
                'INCUS_CONF' => (string) env('INCUS_CONF', '/var/lib/imnehnet-incus'),
                'LANG' => 'C.UTF-8',
                'LC_ALL' => 'C.UTF-8',
            ]);

            // Set before running, and only when there is something to set. Passing an
            // empty string closes stdin, which a command that waits for input would
            // read as end of file rather than as "nothing to send".
            if ($input !== null) {
                $process->setInput($input);
            }

            $process->setTimeout($timeoutSeconds);
            $process->run();

            return [
                'successful' => $process->isSuccessful(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->isSuccessful() ? '' : 'Command unavailable or failed.',
            ];
        } catch (ProcessTimedOutException) {
            return ['successful' => false, 'stdout' => '', 'stderr' => 'Command timed out.'];
        } catch (Throwable) {
            return ['successful' => false, 'stdout' => '', 'stderr' => 'Command unavailable or failed.'];
        }
    }
}
