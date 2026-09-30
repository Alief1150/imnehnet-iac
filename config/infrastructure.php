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

    'wazuh_api_url' => env('WAZUH_API_URL', 'https://127.0.0.1:55000'),
    'wazuh_api_username' => env('WAZUH_API_USERNAME'),
    'wazuh_api_password' => env('WAZUH_API_PASSWORD'),
    'wazuh_api_configured' => (bool) env('WAZUH_API_URL')
        && (bool) env('WAZUH_API_USERNAME')
        && (bool) env('WAZUH_API_PASSWORD'),
];
