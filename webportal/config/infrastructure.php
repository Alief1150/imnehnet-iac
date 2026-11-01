<?php

return [
    'probe_timeout_seconds' => 3,

    // Cached for a short window so a dashboard refresh does not fan out into
    // one probe per widget.
    'resource_cache_seconds' => 10,

    // Thresholds used to turn raw percentages into the status vocabulary
    // defined in the specification: healthy / degraded / offline / unknown.
    'resource_degraded_percent' => 75,
    'resource_critical_percent' => 90,

    /*
     * Connection the lab inventory is read from.
     *
     * In production this is the infrastructure database, which owns
     * lab_environments. The test suite points it at its own throwaway
     * database instead, because two in-memory SQLite connections are two
     * separate databases and a record written through one would be invisible
     * to the other.
     */
    'environment_connection' => env('ENVIRONMENT_CONNECTION', 'imnehnet'),

    /*
     * Never operated on by the portal. The default workspace holds the state
     * of the reference container, so aiming a destroy at it would take down a
     * container that exists to be a baseline for comparison.
     */
    'protected_workspaces' => ['default'],

    'protected_containers' => ['lab-tofu-test'],

    /*
     * OpenTofu drives the container lifecycle.
     *
     * The working directory holds the state of exactly one selected
     * workspace, so every command that switches workspace, plans, applies or
     * destroys runs under a single exclusive lock. Without it two queue jobs
     * could interleave a workspace switch and apply against the wrong
     * environment.
     */
    'tofu' => [
        'directory' => env('TOFU_DIRECTORY', '/home/iaas/imnehnet-iac/tofu'),
        'binary' => env('TOFU_BINARY', 'tofu'),
        'lock_file' => env('TOFU_LOCK_FILE', storage_path('framework/tofu.lock')),
        // apply waits on an image download on a cold host, so this is generous
        // on purpose. It is still bounded, because an unbounded command would
        // hold the lock forever.
        'timeout_seconds' => (int) env('TOFU_TIMEOUT_SECONDS', 900),
        'lock_wait_seconds' => (int) env('TOFU_LOCK_WAIT_SECONDS', 300),
    ],

    /*
     * Ansible configures a container that already exists. Provisioning and
     * lifecycle are OpenTofu's job; configuration is Ansible's.
     */
    'ansible' => [
        'directory' => env('ANSIBLE_DIRECTORY', '/home/iaas/imnehnet-iac/ansible'),
        'binary' => env('ANSIBLE_BINARY', 'ansible-playbook'),
        // A reachability check is an ad-hoc module run, not a playbook, so it
        // needs the ad-hoc client. Running it through ansible-playbook produces
        // a usage error, which would read as "cannot reach the compute plane".
        'ping_binary' => env('ANSIBLE_PING_BINARY', 'ansible'),
        'playbook' => env('ANSIBLE_PLAYBOOK', 'playbooks/student-bootstrap.yml'),
        'inventory_group' => env('ANSIBLE_INVENTORY_GROUP', 'lab_env'),
        'timeout_seconds' => (int) env('ANSIBLE_TIMEOUT_SECONDS', 600),
    ],

    // The Incus client used for per-container operations such as resolving a
    // uid inside a container. Kept next to the remote so the two are not set to
    // different planes by accident.
    'incus_binary' => env('INCUS_BINARY', 'incus'),

    'incus_remote' => 'lab-env:',

    // The attacker plane, where the attacker half of a pair is created.
    //
    // Named beside the victim remote rather than inside the lab configuration below,
    // because it is a property of the installation — which machine is the attacker
    // plane — and not of any one lab. Provisioning reads it, and the Infrastructure
    // page reads it to show where that plane is actually reachable.
    //
    // The address is not written down here. It comes from the remote's URL, so the page
    // cannot claim a plane is at an address the portal does not use to reach it.
    'attacker_plane_remote' => env('ATTACKER_PLANE_REMOTE', 'lab-atk'),

    'attacker_plane_ssh_alias' => env('ATTACKER_PLANE_SSH_ALIAS', 'lab-atk'),

    // Network the student containers are attached to on the compute plane.
    'incus_network' => env('INCUS_NETWORK', 'incusbr0'),

    /*
     * The lab pair.
     *
     * A lab is two containers on two machines, and the two halves share a last octet
     * so that a pair is recognisable at a glance in a log: a victim at 10.20.0.n and
     * an attacker at 10.30.0.n. Both subnets are stated as prefixes because the
     * allocator appends the octet, and a pair is only ever drawn from these two.
     */
    // Three octets, not four. The allocator appends the shared octet to this, so a
    // value that already included the host part produced 10.20.0.0.100.
    'victim_subnet' => env('VICTIM_SUBNET', '10.20.0'),

    'attacker_subnet' => env('ATTACKER_SUBNET', '10.30.0'),

    'incus_attacker_remote' => env('INCUS_ATTACKER_REMOTE', 'lab-atk'),

    /*
     * The attacker plane, reached over SSH.
     *
     * The lab firewall lives on LAB-ATK rather than here, so permitting a pair means
     * running nft over there. The alias resolves to a key-based entry in ~/.ssh/config
     * rather than to a password, because a host credential stored for the portal to
     * read would be a credential in the repository.
     */
    'attacker_plane_ssh_alias' => env('ATTACKER_PLANE_SSH_ALIAS', 'lab-atk'),

    /*
     * Where the firewall ruleset lives on the attacker plane.
     *
     * Named because laa-ctf-config.service loads this file at boot. A pair added to
     * the running set and not written here is a pair that stops existing at the next
     * restart, and the lab it belongs to then silently cannot reach its own victim.
     */
    'firewall_ruleset_path' => env('FIREWALL_RULESET_PATH', '/etc/laa-ctf/laa-ctf.nft'),

    // How long a command against the attacker plane may take before it is abandoned.
    // Generous, because nft --check on the full ruleset is not instantaneous.
    'attacker_plane_timeout_seconds' => (int) env('ATTACKER_PLANE_TIMEOUT_SECONDS', 20),

    /*
     * Container resource limits are decided by the portal, not by the student.
     *
     * A student chooses whether an environment exists, not how large it is. The
     * create form used to expose CPU and memory as inputs, which is both contrary
     * to that and a way for one student to ask for more than the compute plane
     * holds. These values are applied to every environment the portal creates.
     *
     * The disk limit was never a form field but is set here too, so all three
     * limits are stated together and cannot drift apart.
     */
    'default_cpu_limit' => (int) env('DEFAULT_CPU_LIMIT', 2),
    'default_memory_limit_mb' => (int) env('DEFAULT_MEMORY_LIMIT_MB', 2048),
    'default_disk_limit_gb' => (int) env('DEFAULT_DISK_LIMIT_GB', 8),

    // Management plane (this host) and compute plane (LAB-ENV) are probed
    // separately so an operator can tell which side of the split is failing.
    'iaas_health_url' => env('IAAS_HEALTH_URL', 'http://127.0.0.1:8000/up'),
    'lab_env_api_url' => env('LAB_ENV_API_URL', 'https://192.168.100.20:8443/1.0'),

    // The attacker plane's Incus API, asked the same way LAB-ENV is asked.
    //
    // LAB-ATK used to be read through `incus query lab-atk:/1.0`, which only works for the
    // account that owns the client certificates. The portal runs as the web server's user,
    // which owns no such identity, so the card read Unknown while the plane was fine. An
    // unauthenticated GET to /1.0 answers 200 whenever the API is up — measured from this
    // host against both planes — so this is the same reachability question at a privilege
    // the portal actually has, and the default is the URL the registered incus remote uses.
    'attacker_plane_api_url' => env('ATTACKER_PLANE_API_URL', 'https://192.168.100.30:8443/1.0'),

    // Published ports are bound on the host running Incus, so the address a
    // student connects to is the compute plane's management address.
    'lab_env_access_host' => env('LAB_ENV_ACCESS_HOST', '192.168.100.20'),

    // Address a student actually types. Published ports are forwarded from
    // the management plane, so this is the portal address, not the lab host.
    'portal_public_host' => env('PORTAL_PUBLIC_HOST', '100.77.79.18'),

    // Public base address the portal is reached at, used to build links to the
    // monitoring consoles so an operator can open the source of a figure.
    'console_base_url' => env('CONSOLE_BASE_URL', 'http://100.77.79.18'),

    'prometheus_console_port' => env('PROMETHEUS_CONSOLE_PORT', '9090'),
    'grafana_console_port' => env('GRAFANA_CONSOLE_PORT', '3000'),
    'wazuh_console_port' => env('WAZUH_CONSOLE_PORT', '55000'),

    'wazuh_health_url' => env('WAZUH_HEALTH_URL', 'https://127.0.0.1:55000'),

    // The dashboard is a different service on a different port than the API, so
    // it gets its own probe: the login page answering 200 means the interface
    // operators actually open is up.
    'wazuh_dashboard_url' => env('WAZUH_DASHBOARD_URL', 'https://127.0.0.1:8443/app/login'),

    'prometheus_health_url' => env('PROMETHEUS_HEALTH_URL', 'http://127.0.0.1:9090/-/healthy'),
    'prometheus_query_url' => env('PROMETHEUS_QUERY_URL', 'http://127.0.0.1:9090/api/v1/query'),
    'prometheus_range_url' => env('PROMETHEUS_RANGE_URL', 'http://127.0.0.1:9090/api/v1/query_range'),

    // Window and resolution of the sparklines shown on the dashboard.
    'resource_trend_minutes' => 30,
    'resource_trend_step_seconds' => 60,

    'grafana_health_url' => env('GRAFANA_HEALTH_URL', 'http://127.0.0.1:3000/api/health'),
    'blackbox_health_url' => env('BLACKBOX_HEALTH_URL', 'http://127.0.0.1:9115/-/healthy'),

    /*
     * The web terminal.
     *
     * ttyd serves a real shell, so it is bound to loopback and given a
     * credential that is regenerated per session. Neither of those reaches the
     * browser directly: the browser is meant to connect through a relay, and
     * the relay is off unless it is deliberately switched on, because a
     * listening port that serves a shell is not something to enable by
     * accident.
     *
     * The shell user is the account inside the container, not on the management
     * plane. Its uid is resolved from the container at session start and a
     * missing account refuses the session rather than falling back to root.
     */
    'terminal' => [
        'binary' => env('TERMINAL_BINARY', '/home/iaas/bin/ttyd'),
        'shell_user' => env('TERMINAL_SHELL_USER', 'student'),
        'session_ttl_minutes' => (int) env('TERMINAL_SESSION_TTL_MINUTES', 15),
        'max_sessions_per_user' => (int) env('TERMINAL_MAX_SESSIONS_PER_USER', 2),
        // These bounds must match the slots declared in deploy/apache/
        // imnehnet-portal.conf. A port inside this range that the vhost does
        // not proxy is a terminal that starts and then returns 502, so the two
        // are one setting expressed in two places rather than two settings.
        'port_range_min' => (int) env('TERMINAL_PORT_RANGE_MIN', 38000),
        'port_range_max' => (int) env('TERMINAL_PORT_RANGE_MAX', 38007),
        'log_directory' => env('TERMINAL_LOG_DIRECTORY', storage_path('logs/terminal')),

        // The password file Apache reads for this slot. Its existence is the lease: the
        // vhost tests for the file on every request, so a slot opens when the portal writes
        // it and closes when the portal deletes it. This replaces a single "port token" map
        // behind a RewriteMap, which could do neither — the map was read once when the server
        // started, so a slot leased after that was never reachable.
        'auth_directory' => env('TERMINAL_AUTH_DIRECTORY', '/var/lib/imnehnet/terminal/htpasswd'),

        // The embedded console's tickets. One file per leased slot, named "<port>-<token>",
        // and the vhost tests for it on every request to /embed/ — the same pattern as the
        // password files above, because it answers the same question: is this slot leased
        // right now. The ticket travels in a cookie rather than the URL, so unlike a query
        // string it is written to neither the access log, nor browser history, nor any
        // Referer. Deleting the file closes the slot with no reload.
        'embed_directory' => env('TERMINAL_EMBED_DIRECTORY', '/var/lib/imnehnet/terminal/embed'),

        // The shell runner that starts administrative shells when the request comes from the
        // web server, which runs as a different account than the host's owner.
        //
        // The runner runs as that account and is reached over a unix socket. It re-decides
        // everything — which node, which port, which processes may be signalled — because a
        // check the caller performs is a check the caller can skip.
        'runner_socket' => env('TERMINAL_RUNNER_SOCKET', '/var/lib/imnehnet/terminal/runner.sock'),

        /*
         * The account an administrative shell has to be started by.
         *
         * It is the account that can sudo and the only one that can read the key the lab nodes
         * are reached with, so the portal does not start these shells itself when it is running
         * as the web server's user: it asks a runner that runs as this account to start them.
         *
         * Running php-fpm as this account instead would make every web vulnerability on the
         * host root-adjacent. Letting the web start the shell itself would open one with no
         * sudo and no key while reporting it as administrative. Neither is acceptable, and
         * naming the account here is what makes the runner's socket and its own check line up.
         */
        'host_account' => env('TERMINAL_HOST_ACCOUNT', 'iaas'),

        // Whether the portal hands a browser a terminal address at all.
        //
        // This is the switch for exposing a shell, and it is off by default
        // because getting it wrong means serving a shell rather than serving
        // nothing. The prerequisites are listed rather than assumed, because
        // enabling this on a host where they do not hold would produce a
        // terminal that silently never connects:
        //
        //   - Apache has proxy_fcgi, proxy_wstunnel and rewrite enabled
        //   - the vhost's slots match port_range_min..port_range_max
        //   - this directory is writable by the portal and readable by Apache
        //   - the host is on HTTPS, or the keystrokes cross the LAN in clear
        'browser_access_enabled' => (bool) env('TERMINAL_BROWSER_ACCESS', false),
    ],

    // How many environments one student may hold at once. Registration is open,
    // so without this any account that reaches the portal can ask the compute
    // plane for an unbounded number of containers. Administrators are not
    // counted; they provision for other people and for testing.
    'student_environment_limit' => (int) env('STUDENT_ENVIRONMENT_LIMIT', 3),

    'wazuh_api_url' => env('WAZUH_API_URL', 'https://127.0.0.1:55000'),
    'wazuh_api_username' => env('WAZUH_API_USERNAME'),
    'wazuh_api_password' => env('WAZUH_API_PASSWORD'),
    'wazuh_indexer_url' => env('WAZUH_INDEXER_URL', 'https://127.0.0.1:9200'),
    'wazuh_indexer_username' => env('WAZUH_INDEXER_USERNAME'),
    'wazuh_indexer_password' => env('WAZUH_INDEXER_PASSWORD'),
    'wazuh_api_configured' => (bool) env('WAZUH_API_URL')
        && (bool) env('WAZUH_API_USERNAME')
        && (bool) env('WAZUH_API_PASSWORD'),
];
