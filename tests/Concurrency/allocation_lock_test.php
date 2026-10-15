<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir().'/imnehnet-concurrency-'.bin2hex(random_bytes(8));
$locks = $tmp.'/locks';
$dbPath = $tmp.'/testing.sqlite';

mkdir($locks, 0700, true);

try {
    $db = new PDO('sqlite:'.$dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec(
        'CREATE TABLE allocations (
            worker_id TEXT NOT NULL,
            octet INTEGER NOT NULL UNIQUE
        )'
    );
    $db = null;

    $processes = [];

    foreach (['worker-a', 'worker-b'] as $id) {
        $command = [
            PHP_BINARY,
            $root.'/tests/Concurrency/allocation_lock_worker.php',
            $dbPath,
            $locks,
            $id,
        ];

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $root
        );

        if (! is_resource($process)) {
            throw new RuntimeException("Cannot start $id");
        }

        fclose($pipes[0]);

        $processes[] = [
            'id' => $id,
            'process' => $process,
            'stdout' => $pipes[1],
            'stderr' => $pipes[2],
        ];
    }

    $results = [];

    foreach ($processes as $entry) {
        $output = stream_get_contents($entry['stdout']);
        $error = stream_get_contents($entry['stderr']);

        fclose($entry['stdout']);
        fclose($entry['stderr']);

        $exit = proc_close($entry['process']);

        if ($exit !== 0) {
            throw new RuntimeException(
                $entry['id']." failed: ".$error
            );
        }

        $results[] = json_decode(
            trim($output),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    $octets = array_column($results, 'octet');

    if (count(array_unique($octets)) !== 2) {
        throw new RuntimeException('Duplicate octet allocated.');
    }

    foreach ($results as $result) {
        echo $result['worker'].': octet '.$result['octet'].PHP_EOL;
    }

    echo "PASS: Two processes received different octets.\n";
} finally {
    // Remove only this test's uniquely generated temporary directory.
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $tmp,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $entry) {
        if ($entry->isDir() && ! $entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }

    rmdir($tmp);
}
