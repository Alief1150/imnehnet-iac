<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Illuminate\Cache\FileStore;
use Illuminate\Cache\FileLock;
use Illuminate\Filesystem\Filesystem;

$dbPath = $argv[1] ?? '';
$lockPath = $argv[2] ?? '';
$workerId = $argv[3] ?? '';

if ($dbPath === '' || $lockPath === '' || $workerId === '') {
    fwrite(STDERR, "Missing arguments\n");
    exit(2);
}

$filesystem = new Filesystem();
$store = new FileStore($filesystem, $lockPath);

$lock = $store->lock('imnehnet:ctf:pair-allocation', 30);

try {
    $lock->block(10, function () use ($dbPath, $workerId): void {
        $db = new PDO('sqlite:'.$dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA busy_timeout = 10000');

        $taken = $db->query(
            'SELECT octet FROM allocations ORDER BY octet'
        )->fetchAll(PDO::FETCH_COLUMN);

        $octet = 150;

        while (in_array($octet, array_map('intval', $taken), true)) {
            $octet++;
        }

        // Deliberately widen the race window.
        usleep(500000);

        $statement = $db->prepare(
            'INSERT INTO allocations (worker_id, octet) VALUES (?, ?)'
        );
        $statement->execute([$workerId, $octet]);

        echo json_encode([
            'worker' => $workerId,
            'octet' => $octet,
        ], JSON_THROW_ON_ERROR).PHP_EOL;
    });
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
