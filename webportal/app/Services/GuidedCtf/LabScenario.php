<?php

namespace App\Services\GuidedCtf;

use App\Infrastructure\Health\CommandRunner;
use App\Models\LabSession;
use RuntimeException;

/**
 * Puts the scenario on a freshly built victim, and checks that it arrived.
 *
 * The victim's content is what the six objectives are about, so it is deployed per lab
 * rather than baked into the image. That has a cost, stated here rather than
 * discovered: a lab is only as good as the thing that deploys it, and a deployment that
 * silently does nothing produces a victim serving nothing and an objective set that can
 * never be completed. So this verifies what it did instead of reporting success for
 * having run.
 *
 * ── Why files are written through a shell ─────────────────────────────────────
 *
 * `incus file push` is served by the container's agent, and that endpoint is not
 * available the moment the container is. A push to a running container, with the
 * destination directory present and the source file read in full, reported a completed
 * transfer and then "Not Found". `incus exec` becomes ready before the file endpoint
 * does, so probing exec does not establish that a push would work, and the failure
 * names a destination that exists for a reason that is not the destination.
 *
 * So content goes in through `sh -c 'cat > "$1"'` with the bytes on stdin. That needs
 * only what exec already needs, retries cheaply, and puts nothing from a file's contents
 * into a command line.
 *
 * The flag is not regenerated on reset. A student who has already read it and is
 * practising the traversal must be handed the same one, or a reset silently invalidates
 * a flag they had already submitted.
 */
class LabScenario
{
    /**
     * The unit the victim serves the course from.
     *
     * Unprivileged with one ambient capability. Port 80 is privileged and the service
     * cannot take it as an ordinary account, and running it as root instead would make
     * the path traversal in the reports viewer reach /etc/shadow — turning the lesson
     * into a way to read the host. One capability takes the port without making the
     * service root, and the bounding set stops it having any other.
     */
    private const UNIT = <<<'UNIT'
        [Unit]
        Description=IMNEHNET CTF web service
        After=network.target

        [Service]
        User=www-data
        Group=www-data
        AmbientCapabilities=CAP_NET_BIND_SERVICE
        CapabilityBoundingSet=CAP_NET_BIND_SERVICE
        Environment=IMNEHNET_LAB=__LAB__
        ExecStart=/usr/bin/python3 /opt/ctf/imnehnet_lab_server.py
        Restart=on-failure
        StandardOutput=journal
        StandardError=journal

        [Install]
        WantedBy=multi-user.target
        UNIT;

    private const SERVER_PATH = '/opt/ctf/imnehnet_lab_server.py';

    private const UNIT_PATH = '/etc/systemd/system/ctf-http.service';

    private const FLAG_PATH = '/opt/ctf/n0te-4-admin/flag.html';

    public function __construct(private readonly CommandRunner $commands) {}

    public function apply(LabSession $session): void
    {
        $this->waitUntilReachable($session);

        $this->createDirectory($session);
        $this->writeServer($session);
        $this->installUnit($session);
        $this->installSsh($session);
        $this->startService($session);

        if ($this->flagOn($session) === null) {
            throw new RuntimeException(
                'the victim was prepared but holds no flag, so the last objective could never be completed',
            );
        }

        $this->assertServing($session);
    }

    /**
     * Install and start SSH on the victim.
     *
     * Not optional, and not decoration. The specification lists 22/tcp and 80/tcp as the
     * victim's exposed services, and the second objective is proved by the student
     * reaching more than one destination port. A port with nothing listening on it
     * writes no log at all, so with only the web service the objective is unprovable
     * however hard a student scans — the lab looks broken at exactly the point where it
     * should start teaching something.
     *
     * LogLevel is set explicitly rather than relied upon. "Connection from" is an INFO
     * line, and Ubuntu happens to default to INFO, but the validator's correctness rests
     * on it and a fact about one distribution is not a guarantee.
     *
     * Tolerated as optional: a lab whose web service works is still worth handing over,
     * and one unreachable objective is better than none. The absence is reported rather
     * than hidden, so an operator can see it.
     */
    private function installSsh(LabSession $session): void
    {
        $installed = $this->inVictim($session, [
            'sh', '-c',
            'DEBIAN_FRONTEND=noninteractive apt-get install -y -qq openssh-server '
            .'>/var/log/imnehnet-ssh-install.log 2>&1',
        ], (int) config('infrastructure.victim_ssh_install_timeout_seconds', 420));

        if (! $installed['successful']) {
            $this->sshShortfall = 'SSH could not be installed, so the port reconnaissance objective cannot be completed.';

            return;
        }

        $this->must($session, 'set the SSH log level', [
            'sed', '-i', 's/^#\?LogLevel.*/LogLevel INFO/', '/etc/ssh/sshd_config',
        ]);

        if (! $this->inVictim($session, ['systemctl', 'enable', '--now', 'ssh'])['successful']) {
            $this->sshShortfall = 'SSH is installed but not running, so the port reconnaissance objective cannot be completed.';
        }
    }

    /**
     * Whether SSH ended up available, and what to say if it did not.
     */
    private ?string $sshShortfall = null;

    /**
     * A note for the operator about anything that did not fully deploy.
     *
     * Kept rather than thrown, because the lab is usable without it and a portal that
     * refuses to hand over a working lab over one missing objective would be worse. It
     * is returned so the failure is visible to whoever looks.
     */
    public function shortfall(): ?string
    {
        return $this->sshShortfall;
    }

    /**
     * Wait until the victim will accept a command at all.
     *
     * OpenTofu's wait_for watches for an address, which says the network came up and
     * nothing else. Retried rather than slept, because the gap varies and a fixed pause
     * is either too short on a busy host or wastefully long on a quiet one.
     */
    private function waitUntilReachable(LabSession $session): void
    {
        $deadline = time() + (int) config('infrastructure.victim_ready_timeout_seconds', 120);

        while (time() < $deadline) {
            if ($this->inVictim($session, ['true'], 10)['successful']) {
                return;
            }

            usleep(2_000_000);
        }

        throw new RuntimeException(
            'the victim container never became reachable, so nothing could be deployed to it',
        );
    }

    /**
     * The web root, including the reports directory the traversal walks out of.
     *
     * Created and checked. A container is created from a plain Ubuntu image with no
     * /opt/ctf at all, and an unchecked mkdir that fails leaves the next step to fail
     * and the message to name the wrong thing.
     */
    private function createDirectory(LabSession $session): void
    {
        $this->must($session, 'create the lab directory', ['mkdir', '-p', '/opt/ctf/reports']);
    }

    private function writeServer(LabSession $session): void
    {
        $source = $this->serverPath();

        if (! is_readable($source)) {
            throw new RuntimeException('the lab web service is missing from the management plane');
        }

        $this->writeOnVictim($session, self::SERVER_PATH, (string) file_get_contents($source));

        $this->must($session, 'make the lab service readable', ['chmod', '0644', self::SERVER_PATH]);

        // The service runs as www-data and reads the flag, so ownership has to allow it.
        // The directories stay traversable rather than tighter, because the traversal
        // deliberately walks out of the web root.
        $this->must($session, 'let the lab service account read the content', [
            'chown', '-R', 'www-data:www-data', '/opt/ctf',
        ]);
    }

    private function installUnit(LabSession $session): void
    {
        $unit = str_replace('__LAB__', $session->victim_container, self::UNIT);

        $this->writeOnVictim($session, self::UNIT_PATH, $unit);

        $this->must($session, 'reload systemd', ['systemctl', 'daemon-reload']);
    }

    private function startService(LabSession $session): void
    {
        $this->must($session, 'start the lab web service', ['systemctl', 'enable', '--now', 'ctf-http']);

        // systemd retries a failing unit quickly and repeatedly, so a pause is needed
        // before asking whether it came up. Without it the first question is asked of a
        // service that has not finished starting, and the answer is a race.
        sleep(4);
    }

    /**
     * The flag, as the victim holds it.
     */
    private function flagOn(LabSession $session): ?string
    {
        $result = $this->inVictim($session, [
            'sh', '-c',
            'grep -oE "[A-Z][A-Z0-9_]*\{[^}]+\}" '.self::FLAG_PATH.' 2>/dev/null | head -1',
        ], 20);

        if (! $result['successful']) {
            return null;
        }

        $flag = trim($result['stdout']);

        return $flag === '' ? null : $flag;
    }

    /**
     * Confirm the service is actually answering.
     *
     * Checked with python3 rather than curl. The victim's image is a minimal Ubuntu and
     * does not ship curl, so a check written with it reports "not serving" for a service
     * that is serving perfectly well — on every lab, which reads as a broken feature
     * rather than as a missing package. python3 is the one interpreter the scenario
     * itself needs, so it is the one that can be relied on.
     */
    private function assertServing(LabSession $session): void
    {
        $result = $this->inVictim($session, [
            'python3', '-c',
            'import urllib.request;'
            .' print(urllib.request.urlopen("http://127.0.0.1/robots.txt", timeout=5).status)',
        ], 25);

        if (! $result['successful'] || ! str_contains($result['stdout'], '200')) {
            throw new RuntimeException(sprintf(
                'the lab web service is not answering on the victim%s. %s',
                $result['stdout'] !== '' ? ' (it replied '.trim($result['stdout']).')' : '',
                $this->diagnose($session),
            ));
        }
    }

    /**
     * Write a file on the victim by piping it into a shell.
     *
     * Retried, because the failure it works around is a timing one and the file endpoint
     * is not the only thing that can be briefly unavailable on a container that has just
     * started.
     */
    private function writeOnVictim(LabSession $session, string $remotePath, string $contents): void
    {
        $attempts = (int) config('infrastructure.victim_write_attempts', 5);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $result = $this->inVictim(
                $session,
                ['sh', '-c', 'cat > "$1"', 'sh', $remotePath],
                45,
                $contents,
            );

            if ($result['successful']) {
                return;
            }

            usleep(1_500_000);
        }

        throw new RuntimeException(sprintf(
            'could not write %s on the victim after %d attempts. %s',
            basename($remotePath),
            $attempts,
            $this->diagnose($session),
        ));
    }

    /**
     * Run a step in the victim and fail loudly if it did not work.
     *
     * Every step goes through here. Checking only the steps that seemed important is how
     * a failed mkdir surfaced as a failed push three lines later, naming the wrong thing.
     *
     * @param  list<string>  $arguments
     */
    private function must(LabSession $session, string $what, array $arguments): void
    {
        if ($this->inVictim($session, $arguments)['successful']) {
            return;
        }

        throw new RuntimeException(sprintf(
            'could not %s on the victim. %s',
            $what,
            $this->diagnose($session),
        ));
    }

    /**
     * What the victim looks like right now, for a failure message.
     *
     * Four facts, because "the lab could not be built" can be true for a dozen reasons
     * and whoever reads it cannot tell which: whether the container is running, whether
     * the web root exists, whether the server arrived, and whether systemd saw the unit.
     */
    private function diagnose(LabSession $session): string
    {
        $state = $this->inVictim($session, [
            'sh', '-c',
            'printf "container=%s webroot=%s server=%s unit=%s" '
            .'"$([ -d /run/systemd/system ] && echo up || echo unreachable)" '
            .'"$([ -d /opt/ctf ] && echo yes || echo no)" '
            .'"$([ -f /opt/ctf/imnehnet_lab_server.py ] && echo yes || echo no)" '
            .'"$([ -f /etc/systemd/system/ctf-http.service ] && echo yes || echo no)"',
        ], 20);

        return $state['successful'] ? trim($state['stdout']) : 'the victim could not be reached at all';
    }

    /**
     * Run a command in the victim.
     *
     * $stdin exists because files are deployed by piping content into a shell rather than
     * through the file API. The bytes go on stdin rather than into the command so nothing
     * in a file's contents can be read as shell syntax.
     *
     * @param  list<string>  $arguments
     * @return array{successful: bool, stdout: string, stderr: string}
     */
    private function inVictim(
        LabSession $session,
        array $arguments,
        int $timeout = 30,
        ?string $stdin = null,
    ): array {
        return $this->commands->run(
            array_merge([$this->binary(), 'exec', $session->victimTarget(), '--'], $arguments),
            $timeout,
            null,
            $stdin,
        );
    }

    private function binary(): string
    {
        return (string) config('infrastructure.incus_binary', 'incus');
    }

    private function serverPath(): string
    {
        return (string) config(
            'infrastructure.lab_server_path',
            base_path('resources/lab/imnehnet_lab_server.py'),
        );
    }
}