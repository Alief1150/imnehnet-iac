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

    // Network the student containers are attached to on the compute plane.
    'incus_network' => env('INCUS_NETWORK', 'incusbr0'),

    // Management plane (this host) and compute plane (LAB-ENV) are probed
    // separately so an operator can tell which side of the split is failing.
    'iaas_health_url' => env('IAAS_HEALTH_URL', 'http://127.0.0.1:8000/up'),
    'lab_env_api_url' => env('LAB_ENV_API_URL', 'https://192.168.100.20:8443/1.0'),

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

        // The file Apache reads to decide which slot is leased and with which
        // token. Its directory has to be shared between the two: the portal
        // writes it, Apache reads it. The vhost refuses a terminal path whose
        // token is not in this file, so an unleased slot cannot be reached.
        'slot_map_file' => env('TERMINAL_SLOT_MAP_FILE', '/var/lib/imnehnet/terminal/slots.map'),

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
    'wazuh_api_configured' => (bool) env('WAZUH_API_URL')
        && (bool) env('WAZUH_API_USERNAME')
        && (bool) env('WAZUH_API_PASSWORD'),
];
