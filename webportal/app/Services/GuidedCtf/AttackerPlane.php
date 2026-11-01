<?php

namespace App\Services\GuidedCtf;

use App\Infrastructure\Health\CommandRunner;
use RuntimeException;

/**
 * Runs commands on the attacker plane.
 *
 * The lab firewall lives on LAB-ATK, not here, so anything that changes the pairing
 * between an attacker and its victim has to be executed over there. This exists as its
 * own class rather than as SSH calls sprinkled through the services because there is a
 * rule that matters and is easy to break by accident: the remote account may run
 * exactly the commands listed below and nothing else. Every call goes through here, so
 * that boundary is stated once and in one place.
 *
 * The boundary, measured rather than asserted:
 *
 *     nft list set / list table / add / delete element   allowed
 *     nft --check -f /dev/stdin                         allowed, parses only
 *     ip route replace 10.20.0.0/24 ...                 allowed
 *     install -m 0644 /dev/stdin laa-ctf.nft             allowed, one fixed destination
 *     /bin/bash -c id                                   refused
 *     nft flush ruleset                                 refused
 *     nft delete table inet incus                       refused
 *
 * An account that could flush the ruleset would be able to remove the isolation the
 * whole lab design rests on, so the refusal is the feature and not an inconvenience.
 *
 * Every binary is named by absolute path. A relative name would not match the sudoers
 * rule, which is a path, and the command would be refused for a reason that has
 * nothing to do with permissions — the failure the first version of this class produced.
 *
 * Key-based, because a password would have to be stored somewhere the repository does
 * not reach, and this project's rule is that host credentials are not committed.
 */
class AttackerPlane
{
    /** Privileged commands the remote account may run, and nothing outside this list. */
    private const PRIVILEGED = [
        '/usr/sbin/nft',
        '/usr/bin/install',
        '/usr/sbin/ip',
    ];

    private const NFT = '/usr/sbin/nft';

    private const INSTALL = '/usr/bin/install';

    public function __construct(private readonly CommandRunner $commands) {}

    /**
     * Read the permitted pairs.
     *
     * @return list<string>
     */
    public function allowedPairs(): array
    {
        $output = $this->output([self::NFT, 'list', 'set', 'inet', 'laa-ctf', 'allowed_pairs']);

        // The set is printed across several lines with a trailing comma on all but the
        // last element, so the body is taken between the braces and split on commas
        // rather than by line.
        if (preg_match('/elements\s*=\s*\{(.*?)\}/s', $output, $matches) !== 1) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $matches[1]))));
    }

    public function pairIsAllowed(string $attackerIp, string $victimIp): bool
    {
        foreach ($this->allowedPairs() as $pair) {
            if (str_contains($pair, $attackerIp) && str_contains($pair, $victimIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add one pair to the live set.
     */
    public function allowPair(string $attackerIp, string $victimIp): void
    {
        if ($this->pairIsAllowed($attackerIp, $victimIp)) {
            return;
        }

        $this->run([
            self::NFT, 'add', 'element', 'inet', 'laa-ctf', 'allowed_pairs',
            '{ '.$attackerIp.' . '.$victimIp.' }',
        ]);
    }

    public function denyPair(string $attackerIp, string $victimIp): void
    {
        if (! $this->pairIsAllowed($attackerIp, $victimIp)) {
            return;
        }

        $this->run([
            self::NFT, 'delete', 'element', 'inet', 'laa-ctf', 'allowed_pairs',
            '{ '.$attackerIp.' . '.$victimIp.' }',
        ]);
    }

    /**
     * Write the live set back to the file the boot unit loads.
     *
     * Not a convenience. laa-ctf-config.service loads this file at boot, so a pair
     * added to memory and not written here disappears on the next restart, and that
     * lab's attacker then cannot reach its own victim — with no error anywhere, because
     * nothing asks whether the lab works.
     *
     * Two steps, both of them commands the remote account is allowed to run, and both
     * taking the content on stdin rather than as a filename. The candidate is checked
     * before it is installed, so an invalid ruleset cannot replace one that is
     * currently working: a ruleset that does not parse is worse than a stale one,
     * because loading it at boot would take the whole isolation table away.
     */
    public function persistRuleset(): void
    {
        $contents = $this->rulesetContents();

        $checked = $this->run([self::NFT, '--check', '-f', '/dev/stdin'], 20, $contents);

        if (! $checked['successful']) {
            throw new RuntimeException(
                'The firewall ruleset the portal generated does not parse, so it was not '
                .'installed and the lab pair was not made permanent. Refusing to continue.',
            );
        }

        $installed = $this->run(
            [self::INSTALL, '-m', '0644', '/dev/stdin', $this->rulesetPath()],
            20,
            $contents,
        );

        if (! $installed['successful']) {
            throw new RuntimeException(
                'The lab firewall ruleset could not be written, so this lab would not survive a '
                .'restart of the attacker plane. Refusing to continue.',
            );
        }
    }

    /**
     * The ruleset as the portal maintains it.
     *
     * Written out in full rather than edited, because a file assembled from a template
     * cannot drift from what the portal believes is loaded, and the boot unit reads
     * this rather than whatever is in memory now.
     */
    public function rulesetContents(): string
    {
        $pairs = array_map(
            static fn (string $pair): string => "\t\t\t\t".$pair,
            $this->allowedPairs(),
        );

        $elements = implode(",\n", $pairs);

        return <<<NFT
            # Written by the IMNEHNET portal. Do not edit by hand.
            #
            # laa-ctf-config.service loads this file at boot. Every pair a lab needs must
            # be in it, or after a restart that lab's attacker cannot reach its victim and
            # nothing reports it. The portal adds elements to the live set and then
            # rewrites this file so that the two agree.

            table inet laa-ctf {
            \tset allowed_pairs {
            \t\ttype ipv4_addr . ipv4_addr
            \t\telements = { {$elements} }
            \t}

            \tchain forward {
            \t\ttype filter hook forward priority 10; policy accept;
            \t\tct state established,related accept
            \t\tiifname "incusbr0" ip saddr 10.30.0.0/24 ip daddr 10.20.0.0/24 ip protocol icmp ip saddr . ip daddr @allowed_pairs counter accept comment "paired lab: icmp"
            \t\tiifname "incusbr0" ip saddr 10.30.0.0/24 ip daddr 10.20.0.0/24 meta l4proto tcp th dport { 22, 80 } ip saddr . ip daddr @allowed_pairs counter accept comment "paired lab: ssh+http"
            \t\tiifname "incusbr0" ip saddr 10.30.0.0/24 ip daddr 10.20.0.0/24 counter limit rate 5/second burst 5 packets log prefix "laa-ctf-atk2def-drop: " flags all
            \t\tiifname "incusbr0" ip saddr 10.30.0.0/24 ip daddr 10.20.0.0/24 counter drop comment "cross-student deny"
            \t\toifname "incusbr0" ip saddr 192.168.100.20 ip daddr 10.30.0.0/24 ct state new counter limit rate 5/second burst 5 packets log prefix "laa-ctf-def2atk-drop: " flags all
            \t\toifname "incusbr0" ip saddr 192.168.100.20 ip daddr 10.30.0.0/24 ct state new counter drop comment "victim-initiated deny"
            \t\tiifname "incusbr0" ip saddr 10.30.0.0/24 ip daddr { 192.168.100.0/24, 100.64.0.0/10 } ct state new counter drop comment "attacker to mgmt/tailnet deny"
            \t}

            \tchain input {
            \t\ttype filter hook input priority 10; policy accept;
            \t\tiifname "incusbr0" ip saddr 10.30.0.0/24 ip daddr 192.168.100.30 ct state established,related accept
            \t\tiifname "incusbr0" ip saddr 10.30.0.0/24 ip daddr 192.168.100.30 counter limit rate 5/second burst 5 packets log prefix "laa-ctf-atk2host-drop: " flags all
            \t\tiifname "incusbr0" ip saddr 10.30.0.0/24 ip daddr 192.168.100.30 counter drop comment "attacker to own host management IP deny"
            \t}
            }
            NFT;
    }

    /**
     * Run a command on the attacker plane, privileged only where the remote account is
     * allowed to be.
     *
     * @param  list<string>  $arguments
     * @param  string|null  $stdin  Content for a command that reads stdin rather than a
     *                             filename. Both privileged commands that take content
     *                             are in the allowlist with an explicit path, so a
     *                             caller cannot widen them into writing anything.
     * @return array{successful: bool, stdout: string, stderr: string}
     */
    public function run(array $arguments, int $timeout = 15, ?string $stdin = null): array
    {
        $binary = $arguments[0] ?? '';

        if (! in_array($binary, self::PRIVILEGED, true)) {
            // Refused here rather than at the far end, so an attempt is a clear error
            // in this codebase instead of a permission error from the other host.
            throw new RuntimeException(sprintf(
                'Refusing to run "%s" on the attacker plane. The remote account may only run: %s.',
                $binary,
                implode(', ', self::PRIVILEGED),
            ));
        }

        return $this->commands->run(
            ['ssh', $this->host(), 'sudo', '-n', ...$arguments],
            $timeout,
            null,
            $stdin,
        );
    }

    /**
     * Run a command and return what it printed, raising if it failed.
     *
     * Separate from run() because most callers want the output and not a status flag,
     * and reading ['stdout'] out of a result array at every call site is how an
     * undefined index turns into a type error rather than a failure message.
     *
     * @param  list<string>  $arguments
     */
    private function output(array $arguments, int $timeout = 15): string
    {
        $result = $this->run($arguments, $timeout);

        if (! $result['successful']) {
            throw new RuntimeException(sprintf(
                'The attacker plane refused "%s".',
                implode(' ', array_slice($arguments, 1, 3)),
            ));
        }

        return $result['stdout'];
    }

    private function host(): string
    {
        return (string) config('infrastructure.attacker_plane_ssh_alias', 'lab-atk');
    }

    private function rulesetPath(): string
    {
        return (string) config('infrastructure.firewall_ruleset_path', '/etc/laa-ctf/laa-ctf.nft');
    }
}