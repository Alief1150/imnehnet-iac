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

    'incus_remote' => 'lab-env:',

    // Management plane (this host) and compute plane (LAB-ENV) are probed
    // separately so an operator can tell which side of the split is failing.
    'iaas_health_url' => env('IAAS_HEALTH_URL', 'http://127.0.0.1:8000/up'),
    'lab_env_api_url' => env('LAB_ENV_API_URL', 'https://192.168.100.20:8443/1.0'),

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
