<?php

use App\Support\TerminalEmbedTicket;

/**
 * The embed tickets that let the console live inside the page.
 *
 * Same reasoning as the lease-file test next door: the vhost tests for these files on
 * every request, so writing one opens the slot and deleting it closes it. Everything
 * here is reachable without a listener, and deliberately not tested here: that a frame
 * behind the ticket shows a shell was verified against the running host — page 200 with
 * the ticket cookie and 403 without it, and a 101 websocket upgrade through the gate.
 */

function embedTicketSubject(): array
{
    $directory = storage_path('framework/testing/embed-'.bin2hex(random_bytes(6)));

    return [$directory, new TerminalEmbedTicket($directory)];
}

function clearEmbedTicketDir(string $directory): void
{
    array_map('unlink', (array) glob($directory.'/*'));
    @rmdir($directory);
}

test('an issued ticket is found by its slot, and gone when revoked', function () {
    [$directory, $tickets] = embedTicketSubject();

    expect($tickets->tokenFor(38000))->toBeNull();

    $token = $tickets->issue(38000);

    expect($token)->toMatch('/^[0-9a-f]{32}$/')
        ->and($tickets->tokenFor(38000))->toBe($token)
        ->and($tickets->leasedPorts())->toBe([38000]);

    // The ticket is random per issue: revoking and reissuing must not reproduce it.
    $tickets->revoke(38000);
    $second = $tickets->issue(38000);

    expect($second)->not->toBe($token);

    $tickets->revoke(38000);

    expect($tickets->tokenFor(38000))->toBeNull()
        ->and($tickets->leasedPorts())->toBe([]);

    clearEmbedTicketDir($directory);
});

test('revoking one slot leaves the others leased', function () {
    [$directory, $tickets] = embedTicketSubject();

    $tickets->issue(38000);
    $tickets->issue(38001);
    $tickets->revoke(38000);

    expect($tickets->leasedPorts())->toBe([38001]);

    $tickets->revokeAll();

    expect($tickets->leasedPorts())->toBe([]);

    clearEmbedTicketDir($directory);
});

test('the ticket file carries no secret, only bookkeeping', function () {
    // The vhost tests for existence and never reads the content, so the content must
    // not become a second copy of anything worth stealing.
    [$directory, $tickets] = embedTicketSubject();

    $token = $tickets->issue(38002);
    $body = (string) file_get_contents($tickets->path(38002, $token));

    expect($body)->toContain('port=38002')
        ->and($body)->not->toContain($token);

    $tickets->revokeAll();

    clearEmbedTicketDir($directory);
});
