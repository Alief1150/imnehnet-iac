<?php

use App\Infrastructure\Health\CommandRunner;

test('command runner normalizes locale for noninteractive tools', function () {
    $result = (new CommandRunner)->run([
        PHP_BINARY,
        '-r',
        'exit(getenv("LANG") === "C.UTF-8" && getenv("LC_ALL") === "C.UTF-8" ? 0 : 1);',
    ]);

    expect($result['successful'])->toBeTrue();
});
