<?php

namespace App\Services\GuidedCtf;

use App\Infrastructure\Health\CommandRunner;
use App\Models\LabSession;

/**
 * Reads what a victim container recorded about itself.
 *
 * This is the evidence half of active validation. The portal does not watch the
 * student's terminal and does not ask what they typed; it asks the victim what
 * happened to it, and the victim's own logs are the answer.
 *
 * ── On identifying who made a request ─────────────────────────────────────
 *
 * Worth stating plainly because it constrains everything else: the victim does not
 * see the attacker container's address. Requests arrive at the victim appearing to
 * come from LAB-ATK's LAN address, 192.168.100.30, because traffic between the two
 * networks is routed rather than bridged. Measured, not assumed:
 *
 *     attacker 10.30.0.102 -> victim 10.20.0.102   victim records 192.168.100.30
 *     attacker 10.30.0.103 -> victim 10.20.0.103   victim records 192.168.100.30
 *
 * So a log line cannot be attributed to a particular attacker container by address,
 * and any design that pretends otherwise is wrong.
 *
 * What the same measurement shows is that the attribution is not needed, because
 * the network already enforces it:
 *
 *     attacker B -> victim A      HTTP 000, refused
 *     victim A's log              one request, from A's own traffic
 *
 * Each victim belongs to exactly one student and only that student's attacker can
 * reach it. The victim's log is therefore already scoped to one student before a
 * line is read from it. The address check below is a second gate, not the first:
 * it refuses the management plane, which is the one address that could reach a
 * victim without being a student's attacker, and it accepts anything arriving over
 * the attacker path.
 */
class VictimEvidenceReader
{
    public function __construct(private readonly CommandRunner $commands) {}
    /**
     * Addresses that must never appear in evidence.
     *
     * The management plane can reach a victim for provisioning and for validation,
     * so a request from it proves the portal did something, not that a student
     * attacked anything. The loopback entries are there because a request made from
     * inside the victim is the victim's own traffic.
     *
     * @var list<string>
     */
    private const NEVER_EVIDENCE = ['127.0.0.1', '::1'];

    /**
     * HTTP requests the victim served, most recent last.
     *
     * Only requests from after the session started are considered. A lab container
     * can be reset rather than recreated, so its log may hold traffic from a
     * previous run of the same session, and evidence for an objective the student
     * has not done yet this run must not be inherited.
     *
     * @return list<array{at: string, address: string, method: string, target: string, status: int}>
     */
    public function httpRequests(LabSession $session): array
    {
        $output = $this->exec(
            $session,
            ['journalctl', '-u', 'ctf-http', '--no-pager', '--output=short-iso'],
        );

        if ($output === null) {
            return [];
        }

        $requests = [];

        foreach (explode("\n", $output) as $line) {
            $parsed = $this->parseAccessLine($line);

            if ($parsed === null) {
                continue;
            }

            if ($this->isExcluded($parsed['address'])) {
                continue;
            }

            if ($session->started_at !== null && ! $this->isAfterStart($line, $session)) {
                continue;
            }

            $requests[] = $parsed;
        }

        return $requests;
    }

    /**
     * Destination ports the victim has recorded contact on.
     *
     * Counted across every service that keeps a log, because a port with nothing
     * listening on it writes nothing at all. Two open services means two ports were
     * reached, which is what a port scan demonstrates and what a single request to a
     * known address does not.
     *
     * @return list<int>
     */
    public function contactedServicePorts(LabSession $session): array
    {
        $ports = [];

        foreach ($this->httpRequests($session) as $request) {
            $ports[80] = true;
        }

        foreach ($this->sshConnectionAddresses($session) as $ignored) {
            $ports[22] = true;
        }

        $found = array_keys($ports);

        sort($found);

        return $found;
    }

    /**
     * Addresses the victim's SSH daemon recorded contact from.
     *
     * Read from journald rather than from /var/log/auth.log, because that is where
     * sshd writes on Ubuntu 24.04 and the file does not receive it: querying
     * auth.log for "Connection from" returns nothing on a host where sshd has
     * demonstrably accepted a connection.
     *
     * Both wordings are matched. OpenSSH logs "Connection from ... port ... on ..."
     * on some builds and "Connection closed by ... port ... [preauth]" on others,
     * and the second is the one this host produces. Matching only the first would
     * make objective two unprovable here while appearing to work elsewhere.
     *
     * @return list<string>
     */
    public function sshConnectionAddresses(LabSession $session): array
    {
        $output = $this->exec(
            $session,
            ['journalctl', '-u', 'ssh', '--no-pager', '--output=short-iso'],
        );

        if ($output === null) {
            return [];
        }

        $addresses = [];

        foreach (explode("\n", $output) as $line) {
            $found = preg_match(
                '/(?:Connection from|Connection closed by (?:authenticating user \S+ )?|Connection closed by invalid user \S+ )(?<address>\S+) port \d+/',
                $line,
                $matches,
            );

            if ($found !== 1) {
                continue;
            }

            if ($this->isExcluded($matches['address'])) {
                continue;
            }

            if ($session->started_at !== null && ! $this->isAfterStart($line, $session)) {
                continue;
            }

            $addresses[] = $matches['address'];
        }

        return array_values(array_unique($addresses));
    }

    /**
     * The flag, read from the victim at the moment it is needed.
     *
     * Never cached and never stored. The specification requires that the correct
     * answer not be reachable from the browser, and a value that exists only inside
     * the container is the strongest form of that: there is no column to leak, no
     * cache entry to read and no backup to restore. The cost is one round trip to
     * the victim per submission, which is the right trade.
     */
    public function flag(LabSession $session): ?string
    {
        $output = $this->exec($session, ['cat', '/opt/ctf/n0te-4-admin/flag.html']);

        if ($output === null) {
            return null;
        }

        return preg_match('/[A-Z][A-Z0-9_]*\{[^}]+\}/', $output, $matches) === 1
            ? $matches[0]
            : null;
    }

    /**
     * Pull one access line apart.
     *
     * The service writes in the common log format that every HTTP server uses, so
     * the shape here is the shape of a line from nginx, Apache or Python alike.
     *
     * @return array{at: string, address: string, method: string, target: string, status: int}|null
     */
    private function parseAccessLine(string $line): ?array
    {
        $pattern = '/(?<address>\S+) - - \[[^\]]+\] "(?<method>[A-Z]+) (?<target>\S+) HTTP\/[\d.]+" (?<status>\d{3})/';

        if (preg_match($pattern, $line, $matches) !== 1) {
            return null;
        }

        return [
            'at' => $matches[0],
            'address' => $matches['address'],
            'method' => $matches['method'],
            'target' => $matches['target'],
            'status' => (int) $matches['status'],
        ];
    }

    private function isExcluded(string $address): bool
    {
        if (in_array($address, self::NEVER_EVIDENCE, true)) {
            return true;
        }

        // The management plane provisions and validates, so its traffic is the
        // portal's own. Counting it as a student's attack would let a validation
        // pass on the portal having read a file.
        return $address === (string) config('infrastructure.portal_lan_address', '192.168.100.10');
    }

    /**
     * Whether an entry is recent enough to belong to this run.
     *
     * journalctl is asked for ISO timestamps and the leading token of each line is
     * one, so the comparison is a string comparison between two ISO-8601 values in
     * the same timezone. Parsing them into date objects would be tidier and would
     * also make this method fail on a line that has no timestamp, which the string
     * form does not.
     */
    private function isAfterStart(string $line, LabSession $session): bool
    {
        if ($session->started_at === null) {
            return true;
        }

        $stamp = strtok(trim($line), ' ');

        if ($stamp === false || $stamp === $line) {
            return true;
        }

        try {
            return new \DateTimeImmutable($stamp) >= $session->started_at;
        } catch (\Exception) {
            // An unparseable timestamp is not grounds for rejecting evidence. The
            // other filters still apply, and dropping a real request because its
            // timestamp was odd would fail an objective the student completed.
            return true;
        }
    }

    /**
     * Run a command inside the victim's container on the compute plane.
     *
     * Arguments are passed as an array and never interpolated into a shell string.
     * The container name comes from the session record rather than from a request,
     * and this is the last place it would be used, so a name that failed the
     * environment's own validation on the way in cannot become a command here.
     */
    private function exec(LabSession $session, array $arguments): ?string
    {
        $binary = (string) config('infrastructure.incus_binary', 'incus');

        $target = sprintf(
            '%s:%s',
            $session->victim_remote ?: 'lab-env',
            $session->victim_container,
        );

        $result = $this->commands->run(
            array_merge([$binary, 'exec', $target, '--'], $arguments),
            (int) config('infrastructure.ctf_exec_timeout_seconds', 20),
        );

        return $result['successful'] ? $result['stdout'] : null;
    }
}