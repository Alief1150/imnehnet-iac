<?php

namespace App\Data;

use Symfony\Component\Process\Process;

/**
 * The outcome of one controlled external command.
 *
 * Every external call in this application returns one of these, so a caller
 * can never accidentally treat "the command was started" as "the command
 * worked". The raw output is kept for diagnostics while the sanitised
 * summary is what reaches an administrator, because OpenTofu and Ansible are
 * both chatty enough to print paths, addresses and occasionally environment
 * values that should not land in a browser.
 */
final readonly class ProcessResult
{
    /**
     * @param  list<string>  $command
     */
    public function __construct(
        public array $command,
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public float $durationMs,
        public ?string $errorCode = null,
    ) {}

    public static function fromSymfonyProcess(
        array $command,
        Process $process,
        float $durationMs,
        ?string $errorCode = null,
    ): self {
        return new self(
            command: $command,
            exitCode: $process->getExitCode() ?? -1,
            stdout: $process->getOutput(),
            stderr: $process->getErrorOutput(),
            durationMs: $durationMs,
            errorCode: $errorCode,
        );
    }

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * A command that never started, or one that timed out, is reported
     * separately from one that ran and failed. They need different operator
     * actions: the first is usually a missing binary or a bad path, the second
     * is usually a real infrastructure problem.
     */
    public function timedOut(): bool
    {
        return $this->errorCode === 'timeout';
    }

    public function didNotStart(): bool
    {
        return $this->errorCode === 'not_started';
    }

    /**
     * First meaningful line of the failure, for a one-line summary.
     */
    public function summary(int $limit = 200): string
    {
        if ($this->successful()) {
            return 'Completed successfully.';
        }

        $source = trim($this->stderr) !== '' ? $this->stderr : $this->stdout;

        foreach (preg_split('/\R/', $source) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                return $this->truncate($line, $limit);
            }
        }

        return sprintf('The command exited with code %d.', $this->exitCode);
    }

    /**
     * Output with the shapes that should never reach a browser removed:
     * absolute paths that reveal the filesystem layout, and anything that
     * looks like an assignment of a credential.
     */
    public function sanitized(int $limit = 1200): string
    {
        $text = trim($this->stderr) !== '' ? $this->stderr : $this->stdout;

        if ($text === '') {
            return sprintf('No output. Exit code %d.', $this->exitCode);
        }

        $redacted = preg_replace(
            [
                '/\b[A-Za-z0-9_\-]*(?:password|secret|token|passphrase|private_key)[A-Za-z0-9_\-]*\s*[:=]\s*\S+/i',
                '/\b[A-Fa-f0-9]{32,}\b/',
            ],
            ['[redacted]', '[redacted]'],
            $text
        ) ?? $text;

        return $this->truncate($redacted, $limit);
    }

    private function truncate(string $text, int $limit): string
    {
        return mb_strlen($text) > $limit
            ? mb_substr($text, 0, $limit).'…'
            : $text;
    }

    /**
     * The command as a copyable line for a diagnostics screen. Arguments are
     * already an array, so this only joins them for display.
     */
    public function commandLine(): string
    {
        return implode(' ', array_map(
            fn (string $argument): string => preg_match('/[\s"\']$|^[\s"\']/', $argument) === 1
                ? '"'.$argument.'"'
                : $argument,
            $this->command
        ));
    }
}
