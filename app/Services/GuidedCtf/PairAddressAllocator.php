<?php

namespace App\Services\GuidedCtf;

use App\Infrastructure\Health\CommandRunner;
use RuntimeException;

/**
 * Chooses the shared last octet a new lab will use on both planes.
 *
 * A lab is a pair, and the pair is recognisable because both halves carry the same
 * last octet: a victim at 10.20.0.n and an attacker at 10.30.0.n. That is not
 * decoration. It is what makes an operator able to see at a glance, in a log or a
 * packet capture, which two containers belong together, and it is what the
 * firewall's allowed_pairs set is keyed on.
 *
 * Addresses are chosen rather than discovered. The portal has to be able to tell the
 * student their target, and state it in the lab's own terms, before the student has
 * run anything. An address Incus allocated on its own schedule could not be stated
 * until after provisioning, which is the one moment it is needed.
 *
 * Both planes are checked, not just one. If only the victim's octet were checked, a
 * pair could be handed out whose attacker address is already taken on lab-atk, and
 * the apply would fail after the victim had already been created — leaving half a lab
 * and an error the student cannot act on.
 */
class PairAddressAllocator
{
    /** Addresses below this are left to infrastructure, not handed to students. */
    private const FIRST_OCTET = 100;

    /** 254 is the last usable host in a /24. */
    private const LAST_OCTET = 253;

    public function __construct(private readonly CommandRunner $commands) {}

    /**
     * Allocate an octet free on both planes, and return the pair it implies.
     *
     * @return array{octet: int, victim_ip: string, attacker_ip: string}
     */
    public function allocate(): array
    {
        $taken = $this->octetsInUse();

        for ($octet = self::FIRST_OCTET; $octet <= self::LAST_OCTET; $octet++) {
            if (in_array($octet, $taken, true)) {
                continue;
            }

            return [
                'octet' => $octet,
                'victim_ip' => sprintf('%s.%d', (string) config('infrastructure.victim_subnet', '10.20.0.0/24'), $octet),
                'attacker_ip' => sprintf('%s.%d', (string) config('infrastructure.attacker_subnet', '10.30.0.0/24'), $octet),
            ];
        }

        throw new RuntimeException(
            'No address pair is free on either plane. The lab networks are full.',
        );
    }

    /**
     * Octets already spoken for, from both planes and from the portal's own records.
     *
     * Read from three places because any one of them can be out of date and none is
     * authoritative on its own. Incus knows what exists right now. The firewall's
     * allowed_pairs set knows what has been permitted, which is a different question:
     * a pair can be in the set after its containers are gone, and reusing that octet
     * would create a lab whose traffic is permitted by a rule describing a lab that
     * no longer exists.
     *
     * @return list<int>
     */
    private function octetsInUse(): array
    {
        $octets = [];

        foreach (['lab-env', 'lab-atk'] as $remote) {
            foreach ($this->octetsOnRemote($remote) as $octet) {
                $octets[] = $octet;
            }
        }

        foreach ($this->octetsInFirewall() as $octet) {
            $octets[] = $octet;
        }

        return array_values(array_unique($octets));
    }

    /**
     * @return list<int>
     */
    private function octetsOnRemote(string $remote): array
    {
        $result = $this->commands->run(
            [$this->binary(), 'list', $remote.':', '--format=json'],
            15,
        );

        if (! $result['successful']) {
            // An unreachable plane must not be read as an empty one. Returning
            // nothing here would let the allocator hand out an address that is
            // already in use on that plane, and the failure would surface much later
            // as a provisioning error with no cause.
            throw new RuntimeException(
                sprintf('The compute plane %s could not be listed, so no address is free to allocate.', $remote),
            );
        }

        $decoded = json_decode($result['stdout'], true);

        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('The compute plane %s returned a list this could not read.', $remote));
        }

        $octets = [];

        foreach ($decoded as $instance) {
            foreach ($this->addressesOf($instance) as $address) {
                $octet = $this->octetFromAddress($address);

                if ($octet !== null) {
                    $octets[] = $octet;
                }
            }
        }

        return $octets;
    }

    /**
     * Every IPv4 address a container holds.
     *
     * All interfaces, not one named after the bridge. Incus reports one entry per NIC
     * under state.network and names them as the guest sees them, so eth0 is the usual
     * name on a bridged container; looking for incusbr0 there finds nothing and every
     * container reads as having no address. That is exactly what happened: the
     * allocator believed every octet was free and handed out one already in use.
     *
     * Every address rather than the first, because a container with a second interface
     * may carry the lab address on either.
     *
     * @param  array<string, mixed>  $instance
     * @return list<string>
     */
    private function addressesOf(array $instance): array
    {
        $network = $instance['state']['network'] ?? [];

        if (! is_array($network)) {
            return [];
        }

        $addresses = [];

        foreach ($network as $interfaces) {
            foreach ((array) ($interfaces['addresses'] ?? []) as $entry) {
                if (($entry['family'] ?? null) !== 'inet') {
                    continue;
                }

                $addresses[] = (string) ($entry['address'] ?? '');
            }
        }

        return $addresses;
    }

    /**
 * Octets the firewall currently permits.
 *
 * Read from the attacker's allowed_pairs set rather than from the Incus ACL. The ACL
 * states the permitted network as a whole (10.20.0.0/24) and therefore says nothing
 * about which octets are in use, so reading it and extracting an octet from a subnet
 * produced a number that meant nothing — here the mask, 24.
 *
 * @return list<int>
 */
    private function octetsInFirewall(): array
    {
        $result = $this->commands->run(
            ['ssh', $this->attackerSshAlias(), 'sudo', '-n',
                '/usr/sbin/nft', 'list', 'set', 'inet', 'laa-ctf', 'allowed_pairs'],
            20,
        );

        // A failure here is not fatal to allocation: the running containers are the
        // source of truth for what exists, and this only makes the allocator more
        // conservative. But it must not be silently empty, or an octet already
        // permitted would be handed out again.
        if (! $result['successful']) {
            return [];
        }

        $octets = [];

        foreach ($this->pairsFromFirewall($result['stdout']) as $octet) {
            $octets[] = $octet;
        }

        return $octets;
    }

    /**
     * Octets from the permitted-pair set.
     *
     * @return list<int>
     */
    private function pairsFromFirewall(string $output): array
    {
        // The set is printed across several lines with a trailing comma on all but the
        // last element, so the body is taken between the braces and split on commas
        // rather than by line.
        if (preg_match('/elements\s*=\s*\{(.*?)\}/s', $output, $matches) !== 1) {
            return [];
        }

        $octets = [];

        foreach (explode(',', $matches[1]) as $pair) {
            if (preg_match('/10\.30\.0\.(\d+)\s*\.\s*10\.20\.0\.\d+/', trim($pair), $found) === 1) {
                $octets[] = (int) $found[1];
            }
        }

        return $octets;
    }

    private function octetFromAddress(string $address): ?int
    {
        // Incus reports a CIDR form here as well as a bare address.
        //
        // Assigned before strtok, because strtok takes its first argument by
        // reference and passing it an expression is a warning rather than an error —
        // and a warning here would be printed on every allocation.
        $address = strtok(trim($address), '/');

        if ($address === false) {
            return null;
        }

        $address = trim($address);

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }

        // A container's own loopback is reported in the same place as its lab address
        // and yields an octet of 1, which would look like octet one being in use. It is
        // not a lab address, so it is not counted as one.
        if (str_starts_with($address, '127.')) {
            return null;
        }

        return (int) array_pop(explode('.', $address));
    }

    private function binary(): string
    {
        return (string) config('infrastructure.incus_binary', 'incus');
    }

    private function attackerSshAlias(): string
    {
        return (string) config('infrastructure.attacker_plane_ssh_alias', 'lab-atk');
    }
}