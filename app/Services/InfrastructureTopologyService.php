<?php

namespace App\Services;

use App\Infrastructure\Health\CommandRunner;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The Infrastructure page: what this platform is made of, measured rather than described.
 *
 * ── Why nothing here is a constant ────────────────────────────────────────────────
 *
 * The design this page was built from lists three hosts, five network segments and a
 * provisioning stack of seven technologies. Every one of those is a fact about this
 * installation, and all of them change: a plane is added, a segment is renumbered, a
 * tool is uninstalled. Written as literals they would still look right today and be
 * wrong within the week, and on a page whose entire job is to say what the platform
 * consists of, being confidently wrong is the worst available outcome.
 *
 * So each row is read from the system at the moment it is asked for. A tool that is not
 * installed is not listed. A plane that does not answer is shown as not answering. The
 * list is shorter than the design's, and the shorter list is the true one.
 *
 * ── On the provisioning stack ──────────────────────────────────────────────────────
 *
 * The design names KVM/QEMU, libvirt, Incus, OpenTofu, Ansible, MySQL and Laravel
 * Filament. On this installation libvirt is not installed and QEMU is not on the path —
 * the hypervisor is Incus, not KVM. Those three were dropped rather than shown as
 * installed, because a stack listing is a claim about how the platform is built and
 * showing an absent hypervisor as present would send an operator looking for a
 * hypervisor that is not there.
 */
class InfrastructureTopologyService
{
    /**
     * How long a topology stays fresh.
     *
     * Longer than the health probes next door, because this is slower to collect: it
     * makes one Incus call per plane. Five seconds of staleness on a page describing
     * hardware is a good trade against spawning a process per refresh.
     */
    private const TTL_SECONDS = 30;

    public function __construct(private readonly CommandRunner $commands) {}

    /**
     * @return array<string, mixed>
     */
    public function topology(): array
    {
        return Cache::remember(
            'infrastructure.topology',
            now()->addSeconds(self::TTL_SECONDS),
            fn (): array => [
                'planes' => $this->planes(),
                'hosts' => $this->hosts(),
                'segments' => $this->segments(),
                'stack' => $this->stack(),

                // A string, not a Carbon instance.
                //
                // This array is cached, and cache serialises what it is given. A Carbon
                // object comes back out as __PHP_Incomplete_Class, which Carbon::parse()
                // then refuses — so the page 500s on the header action that reads this
                // timestamp. Storing the formatted value keeps the same information and
                // survives the round trip.
                'collectedAt' => Carbon::now()->toIso8601String(),
            ],
        );
    }

    /**
     * The three planes of the platform.
     *
     * Addresses come from configuration rather than from a table on this page, because
     * the portal already has to know them to provision anything: a lab that cannot be
     * reached cannot be created. Reading them from the same place the provisioning path
     * reads them means the page cannot claim a plane is at an address the platform does
     * not actually use.
     *
     * Health is the existing probe's verdict, not a second opinion. Two answers to
     * "is LAB-ENV up", one on this page and one on the dashboard, would be two chances
     * to disagree in front of the same operator.
     *
     * @return list<array<string, mixed>>
     */
    private function planes(): array
    {
        $health = app(InfrastructureStatusService::class)->summary();

        $planes = [];

        foreach ($this->planeDefinitions() as $plane) {
            $probe = $health[$plane['healthKey']] ?? null;

            // A plane with no entry in the shared probe is asked here rather than reported
            // as unverified. The attacker plane is the case that matters: it postdates the
            // probe, and a card stuck on "Unverified" forever would report a gap in the
            // instrumentation as though it were the plane's state.
            if ($probe === null && isset($plane['remote']) && $plane['remote'] !== null) {
                $probed = $this->probePlane($plane['remote']);

                $status = $probed['status'];
                $detail = $probed['summary'];
            } else {
                $status = match ($probe['status'] ?? null) {
                    'healthy' => 'healthy',
                    'offline', 'down' => 'offline',
                    null => 'unknown',
                    default => 'degraded',
                };

                $detail = $probe['summary'] ?? 'No probe reported for this plane.';
            }

            $planes[] = [
                'key' => $plane['key'],
                'label' => $plane['label'],
                'role' => $plane['role'],
                'address' => $plane['address'],
                'icon' => $plane['icon'],
                'tone' => $plane['tone'],
                'status' => $status,
                'statusLabel' => match ($status) {
                    'healthy' => 'Online',
                    'offline' => 'Offline',
                    'degraded' => 'Degraded',
                    default => 'Unknown',
                },
                'detail' => $detail,

                // Where the verdict came from and how old it is.
                //
                // Not decoration. A probe that says "healthy" without naming its source
                // cannot be acted on when it disagrees with reality, and the operator who
                // has just seen a plane refuse traffic needs to know whether this card was
                // measured seconds or minutes ago. Both were in the page before the
                // redesign and dropping them made the cards harder to trust, not easier
                // to read.
                'source' => $probe['source'] ?? 'incus api',

                // Normalised to a string here, never passed through as a date object.
                //
                // This array is cached and cache serialises what it is given, so a Carbon
                // arrives back as __PHP_Incomplete_Class and Carbon::parse() in the view
                // then refuses it, which 500s the page. Formatting now costs nothing and
                // cannot break on the second read.
                'checkedAt' => $this->asTimestamp(
                    $probe['checked_at'] ?? $probe['checkedAt'] ?? null,
                ),
            ];
        }

        return $planes;
    }

    /**
     * The three hosts, with the address each was measured at.
     *
     * The management host is read from this machine, because it is this machine. The two
     * compute planes are read from the Incus remote's configured URL, which is the
     * address the portal has to use to reach them.
     *
     * @return list<array<string, mixed>>
     */
    private function hosts(): array
    {
        $health = app(InfrastructureStatusService::class)->summary();

        $hosts = [
            [
                'name' => 'IAAS-MGMT',
                'hostname' => (string) gethostname(),
                'role' => 'Management Plane',
                'roleDetail' => 'Portal, orchestration, and the database',
                'address' => $this->localAddress(),
                'icon' => 'server',
                'tone' => 'success',
                // Not `management_plane`, which is the *group* this service is filed
                // under. The summary is keyed by probe, and the probe for this host is
                // `iaas_mgmt`. Using the group name made the row read "Unverified" while
                // the same probe reported the host healthy on the plane card above it.
                'healthKey' => 'iaas_mgmt',
            ],
        ];

        foreach (['LAB-ENV' => 'lab-env', 'LAB-ATK' => 'lab-atk'] as $name => $remote) {
            $hosts[] = [
                'name' => $name,
                'hostname' => $remote,
                'role' => $remote === 'lab-env' ? 'Lab Compute' : 'Attacker Plane',
                'roleDetail' => $remote === 'lab-env'
                    ? 'Student victim environments and the target services'
                    : 'Attacker tooling and paired attacker containers',
                'address' => $this->remoteAddress($remote),
                'icon' => $remote === 'lab-env' ? 'database' : 'flame',
                'tone' => $remote === 'lab-env' ? 'info' : 'error',
                'healthKey' => $remote === 'lab-env' ? 'lab_env' : 'incus_atk',
                'remote' => $remote,
            ];
        }

        foreach ($hosts as $index => $host) {
            $probe = $health[$host['healthKey']] ?? null;

            if ($probe === null && isset($host['remote'])) {
                $probed = $this->probePlane($host['remote']);
                $status = $probed['status'];
                $detail = $probed['summary'];
            } else {
                $status = match ($probe['status'] ?? null) {
                    'healthy' => 'healthy',
                    'offline', 'down' => 'offline',
                    null => 'unknown',
                    default => 'degraded',
                };

                $detail = $probe['summary'] ?? null;
            }

            // A plane whose address could not be determined is shown without one rather
            // than with an empty box: an operator reading "not determined" learns
            // something, an empty cell teaches nothing.
            $hosts[$index]['health'] = $status;
            $hosts[$index]['healthLabel'] = match ($status) {
                'healthy' => 'Healthy',
                'offline' => 'Offline',
                'degraded' => 'Degraded',
                default => 'Unverified',
            };
            $hosts[$index]['addressKnown'] = filled($host['address']);
            $hosts[$index]['detail'] = $detail;
        }

        return $hosts;
    }

    /**
     * Every network segment on both planes, read from Incus.
     *
     * Only the segments that carry an address are listed. Incus also reports the
     * loopback and unmanaged bridges with no IPv4 configuration, and those are not
     * segments the platform uses for anything — listing them would pad the table with
     * rows that say nothing.
     *
     * @return list<array<string, mixed>>
     */
    private function segments(): array
    {
        $segments = [];

        // The management LAN is this machine's own address space, so there is nothing to
        // ask Incus about. Read from the interface instead of declared, for the same
        // reason the hosts are read rather than listed.
        $local = $this->localAddress();

        if ($local !== null && str_starts_with($local, '192.168.100.')) {
            $segments[] = [
                'name' => 'MGMT',
                'cidr' => '192.168.100.0/24',
                'description' => 'Infrastructure and management network',
                'tone' => 'success',
                'source' => 'measured on this interface',
            ];
        }

        foreach ([
            ['lab-env', '10.20', 'success', 'Lab victim networks'],
            ['lab-atk', '10.30', 'warning', 'Attacker container networks'],
        ] as [$remote, $prefix, $tone, $description]) {
            foreach ($this->remoteNetworks($remote) as $network) {
                $address = $network['ipv4'] ?? null;

                if ($address === null) {
                    continue;
                }

                // 10.20.0.1/24 is the bridge's own address; the segment is the range it
                // hands out, which is what an operator needs to read.
                $segments[] = [
                    'name' => sprintf('%s on %s', $network['name'], $remote),
                    'cidr' => $address,
                    'description' => sprintf(
                        'Incus bridge network on %s (%s)',
                        $remote,
                        $description,
                    ),
                    'tone' => $tone,
                    'source' => 'measured from '.$remote,
                ];
            }
        }

        return $segments;
    }

    /**
     * The technologies this platform is actually built on.
     *
     * Each entry is probed. The design's stack named seven; this one lists what answers.
     *
     * @return list<array<string, string>>
     */
    private function stack(): array
    {
        $candidates = [
            ['Incus', 'incus', 'Container runtime for both compute planes', 'layers'],
            ['OpenTofu', 'tofu', 'Declarative provisioning, with per-environment locks', 'boxes'],
            ['Ansible', 'ansible', 'Configuration and scenario deployment', 'workflow'],
            ['MySQL', 'mysql', 'Portal and lab databases', 'database'],
            ['Docker', 'docker', 'Runs the observability stack and Wazuh', 'container'],
            ['Prometheus', 'prometheus', 'Metrics scraped from the nodes', 'activity'],
            ['Grafana', 'grafana', 'Dashboards over the collected metrics', 'bar-chart'],
            ['libvirt', 'virsh', 'KVM hypervisor', 'cpu'],
            ['KVM', 'kvm-ok', 'Hardware virtualisation', 'box'],
        ];

        $stack = [];

        foreach ($candidates as [$label, $binary, $role, $icon]) {
            $path = $this->which($binary);

            if ($path === null) {
                continue;
            }

            $stack[] = [
                'label' => $label,
                'role' => $role,
                'icon' => $icon,
                'path' => $path,
            ];
        }

        return $stack;
    }

    /**
     * Whether a binary is on the path, and where.
     *
     * Asked of the shell rather than of PHP's own lookup, because what this page describes
     * is what the platform invokes as processes. A library extension reporting a
     * capability is a different question from a command an operator can run to check it,
     * and an operator is the reader here.
     */
    private function which(string $binary): ?string
    {
        $result = $this->commands->run(
            ['sh', '-c', 'command -v '.escapeshellarg($binary)],
            2,
        );

        if ($result['successful'] !== true) {
            return null;
        }

        $path = trim($result['stdout']);

        return $path === '' ? null : $path;
    }

    /**
     * Where each plane lives, from the portal's own configuration.
     *
     * @return list<array<string, string>>
     */
    private function planeDefinitions(): array
    {
        $atk = $this->attackerAddress();

        return [
            [
                'key' => 'management',
                'label' => 'Management Plane',
                'role' => 'Portal, orchestration, and the database',
                'address' => $this->localAddress(),
                'icon' => 'server',
                'tone' => 'success',
                'healthKey' => 'iaas_mgmt',
            ],
            [
                'key' => 'lab_env',
                'label' => 'Lab Compute',
                'role' => 'Student victim environments and labs',
                'address' => $this->remoteAddress('lab-env'),
                'icon' => 'box',
                'tone' => 'info',
                'healthKey' => 'lab_env',
                'remote' => 'lab-env',
            ],
            [
                'key' => 'lab_atk',
                'label' => 'Attacker Plane',
                'role' => 'Attacker tooling and paired containers',
                'address' => $atk,
                'icon' => 'flame',
                'tone' => 'error',
                // Not probed by InfrastructureStatusService, which predates the plane.
                // Probed here instead, so the entry is not a permanent "unverified".
                'healthKey' => 'incus_atk',
                'remote' => (string) config('infrastructure.attacker_plane_remote', 'lab-atk'),
            ],
        ];
    }

    /**
     * The networks on a plane, read through Incus's API.
     *
     * Asked as JSON rather than parsed from the table, because the table is formatted for
     * a human and its columns shift between versions — the address column was empty in
     * `incus network list` on this installation while the API returned it perfectly well.
     *
     * @return list<array{string, ?string}>
     */
    private function remoteNetworks(string $remote): array
    {
        $result = $this->commands->run(
            ['incus', 'query', $remote.':/1.0/networks?recursion=1'],
            6,
        );

        if ($result['successful'] !== true || trim($result['stdout']) === '') {
            return [];
        }

        try {
            $decoded = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $networks = [];

        foreach ($decoded as $network) {
            if (! is_array($network)) {
                continue;
            }

            $name = (string) ($network['name'] ?? '');
            $address = $network['config']['ipv4.address'] ?? null;

            if ($name === '') {
                continue;
            }

            $networks[] = [
                'name' => $name,
                'ipv4' => is_string($address) && $address !== '' ? $address : null,
            ];
        }

        return $networks;
    }

    /**
     * This machine's address on the management LAN.
     *
     * Read from the operating system rather than declared, because the whole page is a
     * claim about what this installation looks like right now.
     */
    private function localAddress(): ?string
    {
        $result = $this->commands->run(
            ['sh', '-c', "ip -4 -o addr show scope global | awk '{print \$4}'"],
            2,
        );

        foreach ($this->lines($result['stdout']) as $line) {
            if (str_starts_with($line, '192.168.100.')) {
                return rtrim(explode('/', $line)[0], '/');
            }
        }

        return null;
    }

    /**
     * The address a plane is reached at, asked of that plane.
     *
     * Read from the plane's own /1.0, which reports `environment.addresses` — the
     * addresses it listens on, and therefore the ones the portal actually connects to.
     * So the page cannot claim a plane sits at an address the platform does not use to
     * reach it.
     *
     * Naming the remote is what makes this work. There is no local Incus daemon on this
     * host, so `incus query /1.0/...` fails with "the incus daemon doesn't appear to be
     * started" while `incus query lab-env:/1.0` succeeds. Two other approaches were tried
     * and both fail here for the same reason: `incus remote get` is not a subcommand on
     * this client, and `incus config show` wants the missing local socket.
     */
    private function remoteAddress(string $remote): ?string
    {
        $result = $this->commands->run(
            ['incus', 'query', $remote.':/1.0'],
            5,
        );

        if ($result['successful'] !== true || trim($result['stdout']) === '') {
            return null;
        }

        try {
            $info = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $addresses = is_array($info) ? ($info['environment']['addresses'] ?? null) : null;

        foreach (is_array($addresses) ? $addresses : [$addresses] as $address) {
            if (! is_string($address)) {
                continue;
            }

            // "192.168.100.20:8443" -> "192.168.100.20". A port is not an address and this
            // is an "IP Address" field.
            if (preg_match('#\b(\d{1,3}(?:\.\d{1,3}){3})\b#', $address, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * Whether a plane answers, asked directly.
     *
     * The attacker plane is not in InfrastructureStatusService's probe, which predates it,
     * and reading its card as "Unverified" forever would be reporting the gap as if it
     * were the plane's state. So it is asked here: one HTTP call to the address the
     * portal provisions it at, which is the same question an operator would ask.
     *
     * @return array{status: string, summary: string}
     */
    private function probePlane(string $remote): array
    {
        $address = $this->remoteAddress($remote);

        if ($address === null) {
            return [
                'status' => 'unknown',
                'summary' => 'No Incus remote is registered for this plane, so its address is undetermined.',
            ];
        }

        $url = (string) config('infrastructure.attacker_plane_api_url')
            ?: sprintf('https://%s:8443/1.0', $address);

        $result = $this->commands->run(
            ['curl', '-sk', '-o', '/dev/null', '-w', '%{http_code}', '--max-time', '4', $url],
            6,
        );

        $code = trim($result['stdout']);

        return match (true) {
            $code === '200' => [
                'status' => 'healthy',
                'summary' => 'The Incus API answers on this plane.',
            ],
            $code === '403', $code === '401' => [
                // The plane is up and refusing an unauthenticated request, which is the
                // correct behaviour. Reporting this as offline would be wrong.
                'status' => 'healthy',
                'summary' => 'The Incus API is up and refusing an unauthenticated request, as it should.',
            ],
            default => [
                'status' => 'offline',
                'summary' => 'The Incus API on this plane did not answer.',
            ],
        };
    }

    private function attackerAddress(): ?string
    {
        return $this->remoteAddress((string) config(
            'infrastructure.attacker_plane_remote',
            'lab-atk',
        ));
    }

    /**
     * A timestamp as a string, or null.
     *
     * Handles the case the cache creates. A value that survived serialisation as
     * __PHP_Incomplete_Class is not a date and cannot be parsed as one, so it is
     * reported as absent rather than passed on to be parsed and rejected.
     */
    private function asTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toIso8601String();
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function lines(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn (string $line): bool => $line !== '',
        ));
    }
}