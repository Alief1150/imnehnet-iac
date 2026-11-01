<?php

namespace App\Services\GuidedCtf;

use App\Models\LabSession;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

final class CtfEvidence
{
    /** Caller holds the same lifecycle lock used by provisioning, reset and terminal opening. */
    public function ensure(LabSession $lab): void
    {
        if (! $lab->ctf_epoch) {
            $lab->forceFill(['ctf_epoch' => (string) Str::uuid()])->save();
        }
        $payload = [
            'lab_id' => (int) $lab->id, 'epoch' => $lab->ctf_epoch,
            'attacker_ip' => $lab->attacker_ip, 'victim_ip' => $lab->victim_ip,
            'collector' => base64_encode(file_get_contents(resource_path('lab/ctf_evidence.py'))),
        ];
        $process = $this->process($lab, ['python3', '-c', file_get_contents(resource_path('lab/ctf_setup.py'))]);
        $process->setInput(json_encode($payload, JSON_THROW_ON_ERROR));
        $process->setTimeout(600);
        $process->mustRun();
        $this->assertHealthy($lab);
    }

    public function reset(LabSession $lab): void
    {
        $lab->forceFill(['ctf_epoch' => (string) Str::uuid()])->save();
        $this->ensure($lab);
    }

    public function assertHealthy(LabSession $lab): void
    {
        $this->read($lab);
    }

    public function read(LabSession $lab): array
    {
        $process = $this->process($lab, ['python3', '-c', <<<'PY'
import json, os, stat, subprocess, time
from pathlib import Path
b=Path('/var/lib/imnehnet-ctf')
for p in (b,b/'config.json',b/'state.json'):
    s=p.lstat()
    assert s.st_uid==0 and not s.st_mode & 0o077 and not stat.S_ISLNK(s.st_mode), 'Untrusted evidence permissions'
subprocess.run(['systemctl','is-active','--quiet','imnehnet-ctf-evidence'],check=True)
c=json.loads((b/'config.json').read_text()); s=json.loads((b/'state.json').read_text())
assert 0 <= time.time()-s['heartbeat'] < 15, 'Stale evidence collector'
assert all(c[k]==s[k] for k in ('epoch','lab_id')), 'Evidence generation mismatch'
print(json.dumps(s))
PY]);
        $process->setTimeout(12);
        $process->mustRun();
        $state = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        if (! $lab->ctf_epoch || ($state['epoch'] ?? null) !== $lab->ctf_epoch
            || (int) ($state['lab_id'] ?? 0) !== (int) $lab->id) {
            throw new RuntimeException('Evidence belongs to another lab generation.');
        }

        return $state;
    }

    private function process(LabSession $lab, array $command): Process
    {
        $remote = rtrim((string) config('infrastructure.incus_attacker_remote', 'lab-atk'), ':');
        $environment = $lab->attackerEnvironment;
        $victim = $lab->victimEnvironment;
        if (! $environment || ! $victim || $environment->isProtected() || $victim->isProtected()
            || (int) $environment->user_id !== (int) $lab->user_id
            || (int) $victim->user_id !== (int) $lab->user_id
            || $environment->container_name !== 'attacker-user-'.$lab->user_id
            || $environment->container_name !== $lab->attacker_container
            || $victim->container_name !== 'victim-user-'.$lab->user_id
            || $victim->container_name !== $lab->victim_container
            || rtrim((string) $environment->incus_remote, ':') !== $remote
            || rtrim((string) $lab->attacker_remote, ':') !== $remote
            || ($environment->incus_project ?: 'default') !== 'default'
            || ($victim->incus_project ?: 'default') !== 'default'
            || $environment->internal_ip !== $lab->attacker_ip
            || $victim->internal_ip !== $lab->victim_ip
            || ! filter_var($lab->attacker_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || ! filter_var($lab->victim_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RuntimeException('CTF evidence: pair identity mismatch.');
        }

        return new Process([
            (string) config('infrastructure.incus_binary', 'incus'), '--project', 'default',
            'exec', $remote.':'.$environment->container_name, '--', ...$command,
        ], env: ['INCUS_CONF' => (string) env('INCUS_CONF', '/var/lib/imnehnet-incus')]);
    }
}
