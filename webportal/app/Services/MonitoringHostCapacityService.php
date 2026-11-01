<?php



namespace App\Services;



use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Http;

use Throwable;



final class MonitoringHostCapacityService

{

    private const SELECTOR = 'job="imnehnet_host",instance="192.168.100.1:9100"';



    public function summary(): array

    {

        return Cache::remember('monitoring.host.capacity.v1', 10, function (): array {

            $url = (string) config('infrastructure.prometheus_query_url', '');

            $result = [];



            $up = $this->value($url, 'up{' . self::SELECTOR . '}');

            $online = $up !== null && $up == 1.0;



            foreach (['/' => 'root', '/hdd1' => 'hdd1', '/hdd2' => 'hdd2'] as $mount => $key) {

                $selector = self::SELECTOR . ',mountpoint="' . $mount . '",fstype!="rootfs"';

                $availableMetric = 'node_filesystem_avail_bytes{' . $selector . '}';

                $sizeMetric = 'node_filesystem_size_bytes{' . $selector . '}';

                $ageQuery = 'time() - timestamp(' . $availableMetric . ')';



                $size = $online ? $this->value($url, $sizeMetric) : null;

                $available = $online ? $this->value($url, $availableMetric) : null;

                $age = $online ? $this->value($url, $ageQuery) : null;



                $valid = $size !== null && $size > 0

                    && $available !== null && $available >= 0

                    && $available <= $size && $age !== null

                    && $age >= -15 && $age <= 90;



                $result[$key] = [

                    'percent' => $valid ? round(100 * (1 - $available / $size), 1) : null,

                    'available_gib' => $valid ? round($available / (1024 ** 3), 1) : null,

                    'status' => $valid ? 'live' : 'no_data',

                ];

            }



            $swapSelector = self::SELECTOR;

            $swapTotal = 'node_memory_SwapTotal_bytes{' . $swapSelector . '}';

            $swapFree = 'node_memory_SwapFree_bytes{' . $swapSelector . '}';

            $total = $online ? $this->value($url, $swapTotal) : null;

            $free = $online ? $this->value($url, $swapFree) : null;

            $age = $online ? $this->value($url, 'time() - timestamp(' . $swapFree . ')') : null;



            $valid = $total !== null && $total > 0

                && $free !== null && $free >= 0 && $free <= $total

                && $age !== null && $age >= -15 && $age <= 90;



            $result['swap'] = [

                'percent' => $valid ? round(100 * (1 - $free / $total), 1) : null,

                'available_gib' => $valid ? round($free / (1024 ** 3), 2) : null,

                'status' => $valid ? 'live' : 'no_data',

            ];



            return $result;

        });

    }



    private function value(string $url, string $query): ?float

    {

        if ($url === '') {

            return null;

        }



        try {

            $response = Http::timeout(3)->acceptJson()->get($url, [

                'query' => $query,

            ]);



            if (! $response->successful() || $response->json('status') !== 'success') {

                return null;

            }



            $items = $response->json('data.result', []);

            if (! is_array($items) || count($items) !== 1) {

                return null;

            }



            $value = $items[0]['value'][1] ?? null;

            if (! is_numeric($value)) {

                return null;

            }



            $number = (float) $value;

            return is_finite($number) ? $number : null;

        } catch (Throwable) {

            return null;

        }

    }

}

