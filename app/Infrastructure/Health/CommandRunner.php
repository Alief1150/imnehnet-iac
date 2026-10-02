<?php

namespace App\Infrastructure\Health;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class CommandRunner
{
    /**
     * @param  list<string>  $command
     * @return array{successful: bool, stdout: string, stderr: string}
     */
    public function run(array $command, int $timeoutSeconds = 3, ?string $workingDirectory = null): array
    {
        try {
            // The working directory is part of what makes a command meaningful:
            // running tofu in the wrong directory reports a healthy client while
            // reading the wrong state. Optional so existing callers are unchanged.
            $process = new Process($command, $workingDirectory, env: [
                'LANG' => 'C.UTF-8',
                'LC_ALL' => 'C.UTF-8',
            ]);
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
