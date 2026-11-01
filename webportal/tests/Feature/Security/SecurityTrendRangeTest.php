<?php

use App\Filament\Pages\Security;

it('filters the displayed alert trend to the selected supported window', function (string $range, int $count, float $peak) {
    $now = time();
    $page = new Security;
    $page->range = $range;
    $telemetry = [
        'alerts' => ['hourly' => [
            [$now - 6 * 86400, ['total' => 60]],
            [$now - 2 * 86400, ['total' => 20]],
            [$now - 3600, ['total' => 3]],
            [$now - 600, ['total' => 1]],
        ]],
        'trend' => ['daily' => [[$now - 20 * 86400, 999]]],
    ];
    (new ReflectionProperty(Security::class, 'memoTelemetry'))->setValue($page, $telemetry);

    $chart = $page->alertTrend();

    expect($chart['dots'])->toHaveCount($count);
    expect($chart['peak'])->toBe($peak);
})->with([
    'one day excludes older hours and the unrelated daily curve' => ['24h', 2, 3.0],
    'one week includes the available week of hourly samples' => ['7d', 4, 60.0],
]);

it('shows an empty trend when the selected window has no hourly samples', function () {
    $page = new Security;
    (new ReflectionProperty(Security::class, 'memoTelemetry'))->setValue($page, [
        'alerts' => ['hourly' => [[time() - 2 * 86400, ['total' => 5]]]],
        'trend' => ['daily' => [[time(), 999]]],
    ]);

    $chart = $page->alertTrend();

    expect($chart['empty'])->toBeTrue();
});
