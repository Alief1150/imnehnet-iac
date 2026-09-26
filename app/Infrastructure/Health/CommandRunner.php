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
    public function run(array $command, int $timeoutSeconds = 3): array
    {
        try {
            $process = new Process($command, env: [
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
