<?php

namespace App\Services\GuidedCtf;

use App\Models\LabSession;
use RuntimeException;
use Symfony\Component\Process\Process;

final class AttackerTools
{
    /** Caller holds the session lifecycle lock. */
    public function ensure(LabSession $lab): void
    {
        $environment = $lab->attackerEnvironment;
        $remote = rtrim((string) config('infrastructure.incus_attacker_remote', 'lab-atk'), ':');
        if (! $environment || $environment->isProtected()
            || (int) $environment->user_id !== (int) $lab->user_id
            || $environment->container_name !== 'attacker-user-'.$lab->user_id
            || $environment->container_name !== $lab->attacker_container
            || rtrim((string) $environment->incus_remote, ':') !== $remote
            || rtrim((string) $lab->attacker_remote, ':') !== $remote
            || ($environment->incus_project ?: 'default') !== 'default') {
            throw new RuntimeException('Attacker tools: identitas container tidak cocok.');
        }
        $script = <<<'SH'
set -eu
export DEBIAN_FRONTEND=noninteractive
missing=0
for tool in nmap curl gobuster ip ping; do command -v "$tool" >/dev/null 2>&1 || missing=1; done
test -r /usr/share/wordlists/dirb/common.txt || missing=1
if [ "$missing" = 1 ]; then
    command -v apt-get >/dev/null
    apt-get -o Acquire::Retries=2 -o Acquire::http::Timeout=20 -o Acquire::https::Timeout=20 update
    apt-get -o DPkg::Lock::Timeout=60 -o Acquire::Retries=2 -y install --no-install-recommends nmap curl gobuster dirb iproute2 iputils-ping ca-certificates
    mkdir -p /usr/share/wordlists/dirb
    if [ ! -r /usr/share/wordlists/dirb/common.txt ]; then
        test -r /usr/share/dirb/wordlists/common.txt
        ln -s /usr/share/dirb/wordlists/common.txt /usr/share/wordlists/dirb/common.txt
    fi
fi
if ! id student >/dev/null 2>&1; then useradd -m -U -s /bin/bash student; fi
test "$(id -u student)" -gt 0
runuser -u student -- sh -ec 'nmap --version >/dev/null; curl --version >/dev/null; gobuster version >/dev/null; test -r /usr/share/wordlists/dirb/common.txt'
SH;
        $process = new Process([
            (string) config('infrastructure.incus_binary', 'incus'),
            '--project', 'default', 'exec', $remote.':'.$environment->container_name,
            '--', 'sh', '-ec', $script,
        ], env: ['INCUS_CONF' => (string) env('INCUS_CONF', '/var/lib/imnehnet-incus')]);
        $process->setTimeout(600);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Instalasi tool attacker gagal: '.substr(trim($process->getErrorOutput().' '.$process->getOutput()), -2500));
        }
        app(CtfEvidence::class)->ensure($lab);
    }
}
