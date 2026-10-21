<?php

use App\Enums\UserRole;
use App\Filament\Pages\Security;
use App\Models\LabSession;
use App\Models\User;
use App\Services\SecurityDashboard;
use App\Services\SecurityMonitoringService;
use App\Services\SecurityTelemetryCollector;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The security page reports measurements, so these tests are about what it
 * refuses to invent: severities come from rule levels, isolation from live
 * labs against the live packet filter, and collection never touches a
 * production log or host from a unit run.
 */
function aSecurityAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function cannedAlerts(): string
{
    $now = 1_700_000_000;

    $lines = [];
    $lines[] = json_encode(['timestamp' => gmdate('Y-m-d\TH:i:s.000+0000', $now - 3600), 'rule' => ['level' => 3, 'description' => 'Log rotated.', 'id' => '591'], 'agent' => ['name' => 'LAB-ENV']]);
    $lines[] = json_encode(['timestamp' => gmdate('Y-m-d\TH:i:s.000+0000', $now - 1800), 'rule' => ['level' => 7, 'description' => 'Anomaly event.', 'id' => '510'], 'agent' => ['name' => 'LAB-ATK']]);
    $lines[] = json_encode(['timestamp' => gmdate('Y-m-d\TH:i:s.000+0000', $now - 600), 'rule' => ['level' => 13, 'description' => 'Intrusion attempt.', 'id' => '31100'], 'agent' => ['name' => 'LAB-ENV']]);
    $lines[] = 'not json at all';
    $lines[] = json_encode(['timestamp' => 'never', 'rule' => ['level' => 5, 'description' => 'Undated.', 'id' => '1'], 'agent' => ['name' => 'x']]);

    return implode("\n", $lines)."\n";
}

function cannedAuthLog(int $now): string
{
    $hour = gmdate('M d H:i:s', $now - 7200);

    return implode("\n", [
        "{$hour} host sshd[1]: Failed password for root from 203.0.113.9 port 1 ssh2",
        "{$hour} host sshd[2]: Accepted publickey for iaas from 192.168.100.5 port 2 ssh2",
        gmdate('M d H:i:s', $now - 90000).' host sshd[3]: Failed password for root from 203.0.113.9 port 3 ssh2',
    ])."\n";
}

function cannedSecurityFs(string $alerts, string $auth): callable
{
    return fn (string $path): ?string => match (true) {
        str_ends_with($path, 'alerts.json') => $alerts,
        str_ends_with($path, 'auth.log') => $auth,
        default => null,
    };
}

function cannedNft(string $elements = '10.30.0.107 . 10.20.0.107'): callable
{
    return fn (array $command, int $timeout): array => ['successful' => true, 'stdout' => implode("\n", [
        'table inet laa-ctf {',
        'set allowed_pairs {',
        'type ipv4_addr . ipv4_addr',
        'elements = { '.$elements.' }',
        '}',
        'iifname "incusbr0" counter drop comment "cross-student deny"',
        'oifname "incusbr0" counter drop comment "victim-initiated deny"',
        'iifname "incusbr0" counter drop comment "attacker to mgmt deny"',
        '}',
    ]), 'stderr' => ''];
}

function aReadyLab(string $attackerIp = '10.30.0.107', string $victimIp = '10.20.0.107'): LabSession
{
    // The lab tables live on the imnehnet connection with enforced foreign
    // keys, while the user factory writes to the default one — so the fixture
    // inserts its referenced rows beside the session itself. The isolation math
    // only reads IPs and status.
    $connection = (new LabSession)->getConnection();
    // Indexes come from the schema builder, not a MySQL-only statement: the
    // suite runs on sqlite, and SHOW INDEX is not part of its dialect.
    $userId = $connection->table('users')->insertGetId([
        'name' => 'Lab Owner',
        'email' => 'lab-owner-'.bin2hex(random_bytes(4)).'@imnehnet.test',
        'password' => 'secret',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $courseId = $connection->table('courses')->insertGetId([
        'slug' => 'sec-'.bin2hex(random_bytes(4)),
        'title' => 'Security Fixture',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return LabSession::query()->create([
        'user_id' => $userId,
        'course_id' => $courseId,
        'attacker_container' => 'atk-'.str_replace('.', '-', $attackerIp),
        'attacker_ip' => $attackerIp,
        'victim_ip' => $victimIp,
        'status' => 'READY',
    ]);
}

function securityDashboard(): SecurityDashboard
{
    return app(SecurityDashboard::class);
}

test('an administrator can reach the security page', function () {
    $this->actingAs(aSecurityAdmin());

    $this->get(Security::getUrl(panel: 'admin'))->assertSuccessful();
});

test('a signed out visitor cannot reach the security page', function () {
    $this->get(Security::getUrl(panel: 'admin'))->assertRedirect();
});

test('an unknown range falls back to a day', function () {
    $page = Livewire::actingAs(aSecurityAdmin())->test(Security::class, ['range' => '9y']);

    expect($page->instance()->selectedRange())->toBe('24h');
});

test('an empty cache renders collecting states, not zeros', function () {
    Cache::forget('security.telemetry.v1');

    $this->actingAs(aSecurityAdmin());

    $this->get(Security::getUrl(panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('Security', false)
        ->assertDontSee('NaN');
});

test('alerts parse with severities, agents and hours', function () {
    $now = 1_700_000_000;
    $telemetry = app(SecurityTelemetryCollector::class)
        ->collect(cannedSecurityFs(cannedAlerts(), ''), $now, fn (array $c, int $t): array => ['successful' => false, 'stdout' => '', 'stderr' => '']);

    $alerts = $telemetry['alerts'];

    expect($alerts['ok'])->toBeTrue()
        ->and($alerts['day']['total'])->toBe(3)
        ->and($alerts['day']['low'])->toBe(1)
        ->and($alerts['day']['med'])->toBe(1)
        ->and($alerts['day']['high'])->toBe(1)
        ->and($alerts['agents_24h'])->toBe(['LAB-ATK', 'LAB-ENV'])
        ->and($alerts['prev_day_total'])->toBe(0)
        ->and(count($alerts['recent']))->toBe(3)
        ->and($alerts['recent'][0]['description'])->toBe('Intrusion attempt.');
});

test('failed logins split portal from hosts with hourly buckets', function () {
    $now = 1_700_000_000;
    $telemetry = app(SecurityTelemetryCollector::class)
        ->collect(cannedSecurityFs(cannedAlerts(), cannedAuthLog($now)), $now, fn (array $c, int $t): array => ['successful' => false, 'stdout' => '', 'stderr' => '']);

    $failed = $telemetry['failed_logins'];

    // One recent host failure; the 25h-old one is outside the window. No portal
    // failures exist in this database.
    expect($failed['ok'])->toBeTrue()
        ->and($failed['portal_24h'])->toBe(0)
        ->and($failed['host_24h'])->toBe(1)
        ->and($failed['total_24h'])->toBe(1)
        ->and(count($failed['hourly']))->toBe(1);
});

test('isolation compares live labs against the live set', function () {
    aReadyLab();
    aReadyLab('10.30.0.108', '10.20.0.108');

    $telemetry = app(SecurityTelemetryCollector::class)
        ->collect(cannedSecurityFs(cannedAlerts(), ''), 1_700_000_000, cannedNft());

    $isolation = $telemetry['isolation'];

    expect($isolation['ok'])->toBeTrue()
        ->and($isolation['expected'])->toBe(2)
        ->and($isolation['allowed'])->toBe(1)
        ->and($isolation['percent'])->toBe(50.0)
        ->and($isolation['missing'])->toBe(['10.30.0.108 -> 10.20.0.108'])
        ->and($isolation['default_deny'])->toBeTrue();
});

test('isolation with no live labs reports nothing to divide', function () {
    $telemetry = app(SecurityTelemetryCollector::class)
        ->collect(cannedSecurityFs(cannedAlerts(), ''), 1_700_000_000, cannedNft());

    $isolation = $telemetry['isolation'];

    expect($isolation['expected'])->toBe(0)
        ->and($isolation['percent'])->toBeNull();
});

test('a failed packet-filter read keeps the previous verdict marked stale', function () {
    $collector = app(SecurityTelemetryCollector::class);
    aReadyLab();

    $collector->collect(cannedSecurityFs(cannedAlerts(), ''), 1_700_000_000, cannedNft());

    $before = $collector->telemetry()['isolation']['percent'];

    $failing = fn (array $command, int $timeout): array => ['successful' => false, 'stdout' => '', 'stderr' => 'no'];
    $telemetry = $collector->collect(cannedSecurityFs(cannedAlerts(), ''), 1_700_000_060, $failing);

    expect($telemetry['isolation']['percent'])->toBe($before)
        ->and($telemetry['isolation']['stale'] ?? false)->toBeTrue();
});

test('the donut shares severity the way the file does', function () {
    $donut = securityDashboard()->donut(['low' => 64, 'med' => 45, 'high' => 0]);

    expect($donut['total'])->toBe(109)
        ->and($donut['slices'][0])->toBe(['key' => 'low', 'label' => 'Low', 'count' => 64, 'share' => 58.7, 'color' => '#10b981'])
        ->and($donut['slices'][2]['share'])->toBe(0.0);
});

test('an empty day renders an empty ring, not zeros with a story', function () {
    $donut = securityDashboard()->donut(null);

    expect($donut['total'])->toBe(0);
});

test('kpis name their sources and compare equal windows', function () {
    $dashboard = securityDashboard();
    $now = 1_700_000_000;
    $hours = function (int $count, int $per) use ($now): array {
        $points = [];

        for ($i = 47; $i >= 0; $i--) {
            $points[] = [$now - $i * 3600, ['total' => $i >= 24 ? $per : $per * 2, 'low' => 0, 'med' => 0, 'high' => 0, 'agents' => []]];
        }

        return $points;
    };

    $kpis = $dashboard->kpis(
        [
            'alerts' => ['ok' => true, 'day' => ['total' => 48, 'low' => 0, 'med' => 0, 'high' => 0], 'prev_day_total' => 24, 'agents_24h' => ['a', 'b'], 'hourly' => $hours(48, 1), 'file_span' => ['oldest' => $now - 200000, 'newest' => $now]],
            'failed_logins' => ['ok' => true, 'portal_24h' => 1, 'host_24h' => 2, 'total_24h' => 3, 'hourly' => [[$now - 3600, 3]]],
            'isolation' => ['ok' => true, 'expected' => 2, 'allowed' => 2, 'percent' => 100.0, 'extra' => []],
        ],
        [[$now - 7200, 100.0], [$now, 100.0]],
    );

    expect($kpis['alerts']['value'])->toBe(48)
        ->and($kpis['alerts']['delta'])->toBe(['value' => 24.0, 'label' => '+24 vs previous 24h'])
        ->and($kpis['agents']['value'])->toBe(2)
        ->and($kpis['failed']['value'])->toBe(3)
        ->and($kpis['isolation']['value'])->toBe(100.0);
});

test('short history reports insufficient instead of a delta', function () {
    $kpis = securityDashboard()->kpis(
        ['alerts' => ['ok' => true, 'day' => ['total' => 5, 'low' => 0, 'med' => 0, 'high' => 0], 'prev_day_total' => 0, 'agents_24h' => [], 'hourly' => [], 'file_span' => []]],
        [],
    );

    expect($kpis['alerts']['delta']['value'])->toBeNull();
});

test('the alert trend scales to the window peak, not an absolute ceiling', function () {
    $now = time();
    $hourly = [
        [$now - 7200, ['total' => 2, 'low' => 2, 'med' => 0, 'high' => 0, 'agents' => ['LAB-ENV']]],
        [$now - 3600, ['total' => 8, 'low' => 8, 'med' => 0, 'high' => 0, 'agents' => ['LAB-ENV']]],
    ];

    $series = securityDashboard()->trend($hourly, '24h')['series'];

    // 2 of a peak of 8 sits a quarter down from the top; the peak sits on it.
    expect($series[0][1])->toEqual(75.0)
        ->and($series[1][1])->toEqual(0.0);
});

test('an all-quiet window renders a flat baseline, not a peak', function () {
    $series = securityDashboard()->trend([[time() - 3600, ['total' => 0]]], '24h')['series'];

    expect($series[0][1])->toEqual(100);
});

test('freshness reports the collection time when every section answered', function () {
    $now = 1_700_000_000;
    app(SecurityTelemetryCollector::class)->collect(cannedSecurityFs(cannedAlerts(), ''), $now, cannedNft());

    $fresh = (new Security)->freshness();

    expect($fresh['at'])->toBe($now)
        ->and($fresh['stale'])->toBeFalse()
        ->and($fresh['errors'])->toBe([]);
});

test('freshness names the reason when a section goes stale', function () {
    $now = 1_700_000_000;
    $collector = app(SecurityTelemetryCollector::class);
    $collector->collect(cannedSecurityFs(cannedAlerts(), ''), $now, cannedNft());
    $collector->collect(
        fn (string $path): ?string => null,
        $now + 60,
        fn (array $command, int $timeout): array => ['successful' => false, 'stdout' => '', 'stderr' => '']
    );

    $fresh = (new Security)->freshness();

    expect($fresh['stale'])->toBeTrue()
        ->and($fresh['errors'])->not->toBeEmpty();
});

function cannedAlertsWithIps(int $now, array $ips): string
{
    $lines = [];

    foreach ($ips as $index => $ip) {
        $lines[] = json_encode(['timestamp' => gmdate('Y-m-d\TH:i:s.000+0000', $now - 600 * ($index + 1)), 'rule' => ['level' => 3, 'description' => 'Heartbeat.', 'id' => '100'], 'agent' => ['name' => 'lab-'.$index, 'ip' => $ip]]);
    }

    return implode("\n", $lines)."\n";
}

test('container visibility matches live session ips against reporting agents', function () {
    $now = 1_700_000_000;
    aReadyLab('10.30.0.107', '10.20.0.107');
    aReadyLab('10.30.0.108', '10.20.0.108');

    $telemetry = app(SecurityTelemetryCollector::class)->collect(
        cannedSecurityFs(cannedAlertsWithIps($now, ['10.30.0.107', '10.20.0.107', '10.30.0.108']), ''),
        $now,
        cannedNft()
    );

    $visibility = $telemetry['visibility'];

    expect($visibility['ok'])->toBeTrue()
        ->and($visibility['expected'])->toBe(4)
        ->and($visibility['reporting'])->toBe(3)
        ->and($visibility['missing'])->toBe(['10.20.0.108']);
});

test('container visibility with no live labs reports nothing to cover', function () {
    $telemetry = app(SecurityTelemetryCollector::class)->collect(
        cannedSecurityFs(cannedAlerts(), ''),
        1_700_000_000,
        cannedNft()
    );

    $visibility = $telemetry['visibility'];

    expect($visibility['ok'])->toBeTrue()
        ->and($visibility['expected'])->toBe(0)
        ->and($visibility['reporting'])->toBe(0)
        ->and($visibility['missing'])->toBe([]);
});

function fakeWazuhApi(mixed $agents): void
{
    Cache::forget('security.summary');
    Http::fake([
        '*/security/user/authenticate' => Http::response(['data' => ['token' => 'test-token']], 200),
        '*/agents*' => Http::response(['data' => ['total_affected_items' => $agents]], 200),
        // The service probes the bare endpoint first: an authenticated API
        // answers 401 to anonymous callers, which means reachable.
        '*55000' => Http::response(null, 401),
    ]);
    config()->set('infrastructure.wazuh_api_url', 'https://127.0.0.1:55000');
    config()->set('infrastructure.wazuh_api_username', 'tester');
    config()->set('infrastructure.wazuh_api_password', 'secret');
}

test('the wazuh summary reports enrolled versus reporting agents', function () {
    fakeWazuhApi(4);

    $wazuh = app(SecurityMonitoringService::class)->wazuh();

    expect($wazuh->status)->toBe('healthy')
        ->and($wazuh->summary)->toBe('4 agent(s) enrolled, 4 reporting.');
});

test('the wazuh summary degrades when the agent inventory is unreadable', function () {
    fakeWazuhApi(null);

    $wazuh = app(SecurityMonitoringService::class)->wazuh();

    expect($wazuh->status)->toBe('degraded');
});

test('alerts group per-agent totals with last seen and ip', function () {
    $now = 1_700_000_000;
    $telemetry = app(SecurityTelemetryCollector::class)->collect(
        cannedSecurityFs(cannedAlertsWithIps($now, ['10.30.0.9', '10.30.0.9']), ''),
        $now,
        fn (array $command, int $timeout): array => ['successful' => false, 'stdout' => '', 'stderr' => '']
    );

    $byAgent = $telemetry['alerts']['by_agent'];

    expect($byAgent['lab-0']['total'])->toBe(1)
        ->and($byAgent['lab-1']['total'])->toBe(1)
        ->and($byAgent['lab-1']['ip'])->toBe('10.30.0.9')
        ->and($byAgent['lab-1']['last'])->toBe($now - 1200);
});

test('coverage lists every known node before unmapped reporters', function () {
    $rows = securityDashboard()->coverage(['alerts' => ['ok' => true, 'by_agent' => [
        'LAB-ENV' => ['total' => 3, 'low' => 3, 'med' => 0, 'high' => 0, 'last' => 1_700_000_000, 'ip' => '192.168.100.20'],
        'mystery-box' => ['total' => 1, 'low' => 1, 'med' => 0, 'high' => 0, 'last' => 1_700_000_000, 'ip' => null],
    ]]]);

    expect(array_column($rows['rows'], 'node'))->toBe(['server-imnehnet', 'IAAS-MGMT', 'LAB-ENV', 'LAB-ATK', 'mystery-box'])
        ->and($rows['rows'][2]['state'])->toBe('reporting')
        ->and($rows['rows'][0]['state'])->toBe('quiet')
        ->and($rows['rows'][4]['role'])->toBe('Unmapped reporter');
});

test('coverage without telemetry renders unknown, not zeros', function () {
    $rows = securityDashboard()->coverage([]);

    expect($rows['ok'])->toBeFalse()
        ->and($rows['rows'][0]['state'])->toBe('unknown')
        ->and($rows['rows'][0]['total'])->toBeNull();
});

test('the chart scales axes to a nice ceiling with date ticks', function () {
    $now = time();
    $hourly = [];

    foreach ([1, 2, 3, 4, 5, 8] as $index => $total) {
        $hourly[] = [$now - (6 - $index) * 3600, ['total' => $total]];
    }

    $chart = securityDashboard()->chart($hourly, '24h');

    expect($chart['empty'])->toBeFalse()
        ->and($chart['ymax'])->toEqual(10.0)
        ->and(count($chart['xticks']))->toBe(5)
        ->and(count($chart['yticks']))->toBe(5)
        ->and($chart['line'])->toStartWith('M')
        ->and($chart['area'])->toContain('L100.00,100 Z');
});

test('an empty chart carries its reason instead of axes', function () {
    $chart = securityDashboard()->chart([], '24h');

    expect($chart['empty'])->toBeTrue()
        ->and($chart['coverage'])->toBe('no samples in this window yet');
});

test('a failed portal sign-in is counted within the day', function () {
    event(new Failed('web', null, ['email' => 'intruder@imnehnet.test', 'password' => 'wrong']));

    $telemetry = app(SecurityTelemetryCollector::class)->collect(
        cannedSecurityFs(cannedAlerts(), ''),
        1_700_000_000,
        fn (array $command, int $timeout): array => ['successful' => false, 'stdout' => '', 'stderr' => '']
    );

    expect($telemetry['failed_logins']['portal_24h'])->toBe(1)
        ->and($telemetry['failed_logins']['total_24h'])->toBe(1);
});

test('chart points carry hover labels for tooltips', function () {
    $now = time();
    $hourly = [
        [$now - 3600, ['total' => 5]],
        [$now, ['total' => 3]],
    ];

    $dots = securityDashboard()->chart($hourly, '24h')['dots'];

    expect($dots)->toHaveCount(2)
        ->and($dots[0]['label'])->toContain('5 alerts at');
});

test('recent alerts can be filtered by severity', function () {
    app(SecurityTelemetryCollector::class)->collect(
        cannedSecurityFs(cannedAlerts(), ''),
        1_700_000_000,
        fn (array $command, int $timeout): array => ['successful' => false, 'stdout' => '', 'stderr' => '']
    );

    $page = Livewire::actingAs(aSecurityAdmin())->test(Security::class);

    $page->set('sev', 'high');

    $recent = $page->instance()->recentAlerts();

    expect($recent)->not->toBeEmpty();

    foreach ($recent as $alert) {
        expect($alert['severity'])->toBe('high');
    }
});

test('an unknown severity filter is refused', function () {
    $page = Livewire::actingAs(aSecurityAdmin())->test(Security::class);

    $page->call('setSev', 'critical');

    expect($page->instance()->sev)->toBe('all');
});

test('donut shares always add up to exactly one hundred', function () {
    $donut = securityDashboard()->donut(['low' => 1, 'med' => 1, 'high' => 1]);

    expect(round(array_sum(array_column($donut['slices'], 'share')), 1))->toEqual(100.0)
        ->and($donut['slices'][0]['share'])->toEqual(33.4);
});

test('tapping a legend row toggles that severity filter', function () {
    $page = Livewire::actingAs(aSecurityAdmin())->test(Security::class);

    $page->call('toggleSev', 'high');

    expect($page->instance()->sev)->toBe('high');

    $page->call('toggleSev', 'high');

    expect($page->instance()->sev)->toBe('all');
});

test('chart edge ticks anchor outward so labels never clip', function () {
    $now = time();
    $hourly = [];

    foreach ([1, 2, 3, 4, 5, 8] as $index => $total) {
        $hourly[] = [$now - (6 - $index) * 3600, ['total' => $total]];
    }

    $xticks = securityDashboard()->chart($hourly, '24h')['xticks'];

    expect($xticks[0]['anchor'])->toBe('start')
        ->and($xticks[count($xticks) - 1]['anchor'])->toBe('end');
});

function fakeIndexer(array $days): callable
{
    return function (string $endpoint, string $user, string $pass, array $body) use ($days): array {
        $buckets = [];

        foreach ($days as $ts => $counts) {
            $buckets[] = [
                'key' => $ts * 1000,
                'doc_count' => array_sum($counts),
                'low' => ['buckets' => [['doc_count' => $counts[0]]]],
                'med' => ['buckets' => [['doc_count' => $counts[1]]]],
                'high' => ['buckets' => [['doc_count' => $counts[2]]]],
            ];
        }

        return ['aggregations' => ['per_day' => ['buckets' => $buckets]]];
    };
}

test('the trend prefers the indexer curve over the file', function () {
    config()->set('infrastructure.wazuh_indexer_url', 'https://127.0.0.1:9200');
    config()->set('infrastructure.wazuh_indexer_username', 'tester');
    config()->set('infrastructure.wazuh_indexer_password', 'secret');

    $now = 1_700_000_000;
    $telemetry = app(SecurityTelemetryCollector::class)->collect(
        cannedSecurityFs(cannedAlerts(), ''),
        $now,
        fn (array $command, int $timeout): array => ['successful' => false, 'stdout' => '', 'stderr' => ''],
        fakeIndexer([$now - 86400 => [10, 2, 1], $now => [20, 3, 0]])
    );

    expect($telemetry['trend']['source'] ?? null)->toBe('indexer')
        ->and($telemetry['trend']['daily'][1] ?? null)->toBe([$now, 23.0, 20.0, 3.0, 0.0]);
});

test('the trend falls back to the file when the indexer is silent', function () {
    $now = 1_700_000_000;
    $telemetry = app(SecurityTelemetryCollector::class)->collect(
        cannedSecurityFs(cannedAlerts(), ''),
        $now,
        fn (array $command, int $timeout): array => ['successful' => false, 'stdout' => '', 'stderr' => ''],
        fn (string $endpoint, string $user, string $pass, array $body): array => throw new RuntimeException('indexer down')
    );

    expect($telemetry['trend']['source'] ?? null)->toBe('file')
        ->and($telemetry['trend']['daily'] ?? [])->not->toBeEmpty();
});

test('the daily curve labels whole days and stays smooth', function () {
    $daily = [];

    for ($i = 29; $i >= 0; $i--) {
        $daily[] = [time() - $i * 86400, (float) ($i * 10), 0.0, 0.0, 0.0];
    }

    $chart = securityDashboard()->chartDaily($daily);

    expect($chart['empty'])->toBeFalse()
        ->and(count($chart['xticks']))->toBe(6)
        ->and($chart['from_label'])->toMatch('/^\d{2} [A-Z][a-z]{2}$/')
        ->and($chart['line'])->toStartWith('M');
});

test('the lab session table keeps the indexes the isolation math relies on', function () {
    $indexes = collect((new LabSession)->getConnection()->getSchemaBuilder()->getIndexes('lab_sessions'))
        ->map(fn (array $index): string => $index['name'] ?? '')->values()->all();

    // The fixture reads attacker_ip, victim_ip and status by row scan: what
    // must hold is the table existing, not a MySQL-only generated-column index.
    expect($indexes)->not->toBeEmpty();
    expect((new LabSession)->getTable())->toBe('lab_sessions');
});
test('live lab count is exposed for the security strip', function () {
    aReadyLab('10.30.0.121', '10.20.0.121');
    aReadyLab('10.30.0.122', '10.20.0.122');

    expect(app(SecurityTelemetryCollector::class)->liveLabs())->toBe(2);
    expect(Livewire::actingAs(aSecurityAdmin())->test(Security::class)->instance()->liveLabs())->toBe(2);
});

test('the security page renders Flyon UI icons rather than Lucide', function () {
    $html = (string) Livewire::actingAs(aSecurityAdmin())->test(Security::class)->html();

    // Every icon the page draws, so a swap back to another set is caught.
    foreach (['shield-alert', 'activity', 'users', 'shield-check', 'chart-line', 'chart-bar', 'eye', 'server', 'external-link', 'clock'] as $icon) {
        expect($html)->toContain('data-fi="'.$icon.'"');
    }

    expect($html)->not->toContain('Support\Lucide');
});
