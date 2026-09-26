<?php

return [
    'probe_timeout_seconds' => 3,
    'incus_remote' => 'lab-env:',
    'prometheus_health_url' => env('PROMETHEUS_HEALTH_URL', 'http://127.0.0.1:9090/-/healthy'),
    'grafana_health_url' => env('GRAFANA_HEALTH_URL', 'http://127.0.0.1:3000/api/health'),
    'blackbox_health_url' => env('BLACKBOX_HEALTH_URL', 'http://127.0.0.1:9115/-/healthy'),
    'wazuh_api_url' => env('WAZUH_API_URL'),
    'wazuh_api_configured' => false,
];
