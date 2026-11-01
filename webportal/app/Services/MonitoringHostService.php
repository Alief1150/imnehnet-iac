<?php



namespace App\Services;



use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Http;

use Throwable;



final class MonitoringHostService

{

    private const QUERIES = [

        'cpu' => '100 * (1 - avg(rate(node_cpu_seconds_total{job="imnehnet_host",instance="192.168.100.1:9100",mode="idle"}[2m])))',

        'memory' => '100 * (1 - node_memory_MemAvailable_bytes{job="imnehnet_host",instance="192.168.100.1:9100"} / node_memory_MemTotal_bytes{job="imnehnet_host",instance="192.168.100.1:9100"})',

        'storage' => '100 * (1 - node_filesystem_avail_bytes{job="imnehnet_host",instance="192.168.100.1:9100",mountpoint="/",fstype!="rootfs"} / node_filesystem_size_bytes{job="imnehnet_host",instance="192.168.100.1:9100",mountpoint="/",fstype!="rootfs"})',

    ];



    public function summary(): array

    {

        return Cache::remember('monitoring.physical-host.v1', 10, function (): array {

            $url = (string) config('infrastructure.prometheus_query_url', '');

            $result = [];



            foreach (self::QUERIES as $metric => $query) {

                $result[$metric] = $this->fetch($url, $query);

            }



            return $result;

        });

    }



    private function fetch(string $url, string $query): array

    {

        $empty = [

            'percent' => null,

            'status' => 'no_data',

            'sample_at' => null,

        ];



        if ($url === '') {

            return $empty;

        }



        try {

            $response = Http::timeout(3)->acceptJson()->get($url, [

                'query' => $query,

            ]);



            if (! $response->successful() || $response->json('status') !== 'success') {

                return $empty;

            }



            $samples = $response->json('data.result', []);



            if (! is_array($samples) || count($samples) !== 1) {

                return $empty;

            }



            $sample = $samples[0]['value'] ?? null;



            if (! is_array($sample) || count($sample) < 2 || ! is_numeric($sample[0]) || ! is_numeric($sample[1])) {

                return $empty;

            }



            $timestamp = (float) $sample[0];

            $value = (float) $sample[1];

            $age = microtime(true) - $timestamp;



            if (! is_finite($value) || $age < -15 || $age > 90) {

                return [

                    'percent' => null,

                    'status' => 'stale',

                    'sample_at' => $timestamp,

                ];

            }



            return [

                'percent' => round(max(0, min(100, $value)), 1),

                'status' => 'live',

                'sample_at' => $timestamp,

            ];

        } catch (Throwable) {

            return $empty;

        }

    }

}

