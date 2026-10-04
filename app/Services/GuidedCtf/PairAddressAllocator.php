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
            $octet = $this->octetFromAddress((string) ($instance['state']['network']['incusbr0']['addresses'][0]['address'] ?? ''));

            if ($octet !== null) {
                $octets[] = $octet;
            }
        }

        return $octets;
    }

    /**
     * Octets the firewall currently permits.
     *
     * @return list<int>
     */
    private function octetsInFirewall(): array
    {
        $result = $this->commands->run(
            [$this->binary(), 'query', sprintf('%s:/1.0/network/acl/ctf-attacker', $this->attackerRemote())],
            10,
        );

        // The firewall's state is read from the attacker plane over its API, which is
        // where the rules live. A failure here is not fatal to allocation — the set
        // is an input, not the source of truth — but it must not be silently empty,
        // or an octet already permitted would be handed out again.
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
     * @return list<int>
     */
    private function pairsFromFirewall(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        $octets = [];

        foreach (['ingress', 'egress'] as $direction) {
            foreach ((array) ($decoded[$direction] ?? []) as $rule) {
                // The permitted pairs are expressed in the ACL as the concrete
                // addresses a student may reach. Each ends in the shared octet, so
                // the destination address is what identifies a pair in use.
                $octet = $this->octetFromAddress((string) ($rule['destination'] ?? ''));

                if ($octet !== null) {
                    $octets[] = $octet;
                }
            }
        }

        return $octets;
    }

    private function octetFromAddress(string $address): ?int
    {
        // Incus reports a CIDR form here as well as a bare address.
        $address = strtok($address, '/');

        if ($address === false || filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }

        return (int) array_pop(explode('.', $address));
    }

    private function binary(): string
    {
        return (string) config('infrastructure.incus_binary', 'incus');
    }

    private function attackerRemote(): string
    {
        return (string) config('infrastructure.incus_attacker_remote', 'lab-atk');
    }
}