<?php

namespace App\Services\GuidedCtf;

use App\Infrastructure\Health\CommandRunner;
use App\Models\LabSession;
use RuntimeException;

/**
 * Puts the scenario on a freshly built victim.
 *
 * The victim's content is what the six objectives are about, so it is applied per lab
 * rather than baked into the image. That has a cost, and it is stated here rather than
 * discovered: a lab is only as good as the thing that deploys it, and a deployment
 * that silently does nothing produces a victim serving nothing and an objective set
 * that can never be completed. So this verifies what it did instead of reporting
 * success for having run.
 *
 * Deployed through Incus directly rather than through Ansible. The playbook route is
 * the design's and should replace this; until it exists, the direct route is the one
 * that has been run against real containers, and an untested playbook would be a worse
 * answer than a tested one. What is verified here is the same either way: the service
 * answers, it is unprivileged, and the victim holds a flag.
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

    public function __construct(private readonly CommandRunner $commands) {}

    public function apply(LabSession $session): void
    {
        $this->pushServer($session);
        $this->installUnit($session);
        $this->startService($session);

        if ($this->flagOn($session) === null) {
            throw new RuntimeException(
                'the victim was prepared but holds no flag, so the last objective could never be completed',
            );
        }

        // The web service answering is the other half of "prepared". A unit that is
        // enabled but failing would leave a lab that provisions successfully and then
        // never records any evidence, and every objective would be unprovable for a
        // reason the student cannot see.
        $this->assertServing($session);
    }

    private function pushServer(LabSession $session): void
    {
        $source = $this->serverPath();

        if (! is_readable($source)) {
            throw new RuntimeException('the lab web service is missing from the management plane');
        }

        $result = $this->commands->run([
            $this->binary(), 'file', 'push', $source,
            $this->target($session).':/opt/ctf/imnehnet_lab_server.py',
        ], 30);

        if (! $result['successful']) {
            throw new RuntimeException('the lab web service could not be copied to the victim');
        }

        $this->inVictim($session, ['chmod', '0644', '/opt/ctf/imnehnet_lab_server.py']);
        $this->inVictim($session, ['mkdir', '-p', '/opt/ctf/reports']);
        // The service runs as www-data and reads the flag, so ownership has to allow
        // it. 0755 on the directories rather than something tighter, because the
        // traversal deliberately walks out of the web root.
        $this->inVictim($session, ['chown', '-R', 'www-data:www-data', '/opt/ctf']);
    }

    private function installUnit(LabSession $session): void
    {
        $unit = str_replace('__LAB__', $session->victim_container, self::UNIT);

        // Written through stdin so the unit never becomes part of a shell command.
        $written = $this->commands->run(
            [
                'ssh', (string) config('infrastructure.victim_plane_ssh_alias', 'lab-env'),
                'sudo', '-n', 'install', '-m', '0644', '/dev/stdin',
                '/etc/systemd/system/ctf-http.service',
            ],
            20,
            null,
            $unit,
        );

        if (! $written['successful']) {
            throw new RuntimeException('the lab service unit could not be installed on the victim');
        }

        $this->inVictim($session, ['systemctl', 'daemon-reload']);
    }

    private function startService(LabSession $session): void
    {
        $this->inVictim($session, ['systemctl', 'enable', '--now', 'ctf-http']);

        // systemd restarts a failing unit quickly and repeatedly, so a short pause is
        // needed before asking whether it came up. Without it the first question is
        // asked of a service that has not finished starting, and the answer is a
        // failure that is really a race.
        sleep(3);
    }

    private function assertServing(LabSession $session): void
    {
        $result = $this->inVictim($session, [
            'curl', '-s', '-o', '/dev/null', '-w', '%{http_code}',
            'http://127.0.0.1/robots.txt',
        ], 20);

        if (! $result['successful'] || ! str_contains($result['stdout'], '200')) {
            throw new RuntimeException('the lab web service is not answering on the victim');
        }
    }

    private function flagOn(LabSession $session): ?string
    {
        $result = $this->inVictim($session, [
            'sh', '-c',
            'grep -oE "[A-Z][A-Z0-9_]*\{[^}]+\}" /opt/ctf/n0te-4-admin/flag.html 2>/dev/null | head -1',
        ], 20);

        if (! $result['successful']) {
            return null;
        }

        $flag = trim($result['stdout']);

        return $flag === '' ? null : $flag;
    }

    /**
     * @param  list<string>  $arguments
     * @return array{successful: bool, stdout: string, stderr: string}
     */
    private function inVictim(LabSession $session, array $arguments, int $timeout = 30): array
    {
        return $this->commands->run(
            array_merge([$this->binary(), 'exec', $this->target($session), '--'], $arguments),
            $timeout,
        );
    }

    private function target(LabSession $session): string
    {
        return $session->victim_remote.':'.$session->victim_container;
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