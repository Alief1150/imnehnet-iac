<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * The cache warmer the scheduler runs every minute.
 *
 * Renders read these two caches and recompute them when lapsed; warming keeps
 * the recompute off the click that asked for the page. The assertions are only
 * that both caches end up filled — the verdicts inside stay the probes' own
 * business, tested where the probes are tested.
 */
test('warming fills the status summary and the topology', function () {
    Cache::forget('infrastructure.status.summary');
    Cache::forget('infrastructure.topology');

    $this->artisan('infrastructure:warm')->assertSuccessful();

    expect(Cache::has('infrastructure.status.summary'))->toBeTrue()
        ->and(Cache::has('infrastructure.topology'))->toBeTrue();
});
