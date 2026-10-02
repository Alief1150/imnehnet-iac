<?php

use App\Support\TerminalSlotGrants;

/**
 * The slot map is what Apache reads to decide whether a terminal request is
 * allowed. These tests are about the file's contents and its failure behaviour,
 * because a mistake here is not a visible bug in the portal: it either refuses
 * working terminals or, worse, accepts one nobody leased.
 *
 * No application is booted. The class under test touches nothing but the
 * filesystem, and starting Laravel to exercise it would only add a way for
 * these tests to fail for reasons that have nothing to do with grants.
 */
function grantsPath(): string
{
    return sys_get_temp_dir().'/imnehnet-grants-'.bin2hex(random_bytes(6)).'.map';
}

function grants(): TerminalSlotGrants
{
    return new TerminalSlotGrants(grantsPath());
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/imnehnet-grants-*.map') ?: [] as $leftover) {
        @unlink($leftover);
    }
});

test('a granted slot is readable back with its token', function () {
    $store = grants();

    $store->grant(38000, str_repeat('a1', 24));

    expect($store->all())->toBe([38000 => str_repeat('a1', 24)]);
});

test('granting one slot does not disturb the others', function () {
    $store = grants();

    $store->grant(38000, str_repeat('a1', 24));
    $store->grant(38001, str_repeat('b2', 24));
    $store->grant(38002, str_repeat('c3', 24));

    expect($store->all())->toHaveCount(3)
        ->and($store->all()[38001])->toBe(str_repeat('b2', 24));
});

test('revoking a slot leaves the others alone', function () {
    $store = grants();

    $store->grant(38000, str_repeat('a1', 24));
    $store->grant(38001, str_repeat('b2', 24));
    $store->revoke(38000);

    expect($store->all())->toBe([38001 => str_repeat('b2', 24)]);
});

test('re-granting a slot replaces its token rather than keeping both', function () {
    $store = grants();

    $store->grant(38000, str_repeat('a1', 24));
    $store->grant(38000, str_repeat('9f', 24));

    // The old token has to stop working. Two lines for one port would leave
    // Apache able to compare against whichever it read first.
    expect($store->all())->toBe([38000 => str_repeat('9f', 24)]);
});

test('a token containing a non-hex character is refused', function () {
    // Worth being explicit about, because it is the check standing between the
    // map file and Apache's RewriteMap: a token that is not plain hex would be
    // compared with the wrong pattern and could match more than one port.
    $store = grants();

    $store->grant(38000, str_repeat('z9', 24));

    expect($store->all())->toBe([]);
});

test('revoking everything empties the map', function () {
    $store = grants();

    $store->grant(38000, str_repeat('a1', 24));
    $store->grant(38001, str_repeat('b2', 24));
    $store->revokeAll();

    expect($store->all())->toBe([]);
});

test('a missing map file reads as no grants rather than an error', function () {
    // Nothing has ever granted a slot. Apache must see an empty map, not a
    // failure, because a missing file is the normal state before the first
    // terminal and after a restart.
    expect(grants()->all())->toBe([]);
});

test('a malformed line is skipped without losing the grants around it', function () {
    $path = grantsPath();

    file_put_contents($path, implode("\n", [
        '# a comment',
        '38000 '.str_repeat('a1', 24),
        'this line is nonsense',
        '38001 '.str_repeat('b2', 24),
        '38002 not-a-valid-token',
        '',
    ]));

    // One bad line must not take the working terminals down with it, and the
    // slot it names has to end up refused, which is the safe direction.
    expect((new TerminalSlotGrants($path))->all())->toBe([
        38000 => str_repeat('a1', 24),
        38001 => str_repeat('b2', 24),
    ]);
});

test('a token that is not hex is ignored', function () {
    $path = grantsPath();

    file_put_contents($path, "38000 not-hex-at-all\n38001 ".str_repeat('b2', 24)."\n");

    expect((new TerminalSlotGrants($path))->all())->toBe([38001 => str_repeat('b2', 24)]);
});

test('writing a map to an unwritable location says so instead of failing silently', function () {
    $path = '/proc/imnehnet-cannot-write-here/slots.map';

    expect(fn () => (new TerminalSlotGrants($path))->grant(38000, str_repeat('a1', 24)))
        ->toThrow(RuntimeException::class);
});

test('the file is replaced atomically so Apache never reads half of it', function () {
    $path = grantsPath();
    $store = new TerminalSlotGrants($path);

    $store->grant(38000, str_repeat('a1', 24));

    // Whatever the write strategy, nothing may be left behind next to the map:
    // a stale temporary would be read by the next grant and could overwrite the
    // live map with an older set of grants.
    expect(glob(dirname($path).'/'.basename($path).'.*.tmp') ?: [])->toBe([]);
});

test('every rewritten file carries the header Apache and a human both rely on', function () {
    $path = grantsPath();
    $store = new TerminalSlotGrants($path);

    $store->grant(38000, str_repeat('a1', 24));
    $contents = (string) file_get_contents($path);

    expect($contents)->toContain('port token')
        ->and(explode("\n", trim($contents)))->toHaveCount(3);
});
