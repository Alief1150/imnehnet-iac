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
# IMNEHNET_P2_URGENT_FIX_V2: repair Kali wordlist alias before any dependency test / apt call.
if [ ! -r /usr/share/wordlists/dirb/common.txt ] && [ -r /usr/share/dirb/wordlists/common.txt ]; then
    mkdir -p /usr/share/wordlists/dirb
    if [ ! -e /usr/share/wordlists/dirb/common.txt ]; then
        ln -s /usr/share/dirb/wordlists/common.txt /usr/share/wordlists/dirb/common.txt
    fi
fi
export DEBIAN_FRONTEND=noninteractive

fail() {
    printf 'CTF_TOOLS_FAIL stage=%s detail=%s\n' "$1" "$2" >&2
    exit 1
}

# Install only packages whose commands or data are missing.
set --

command -v nmap >/dev/null 2>&1 || set -- "$@" nmap
command -v curl >/dev/null 2>&1 || set -- "$@" curl
command -v gobuster >/dev/null 2>&1 || set -- "$@" gobuster
command -v ip >/dev/null 2>&1 || set -- "$@" iproute2
command -v ping >/dev/null 2>&1 || set -- "$@" iputils-ping

if [ ! -r /usr/share/wordlists/dirb/common.txt ] &&
   [ ! -r /usr/share/dirb/wordlists/common.txt ]; then
    set -- "$@" dirb
fi

if [ "$#" -gt 0 ]; then
    command -v apt-get >/dev/null 2>&1 ||
        fail package_manager "apt-get unavailable"

    echo "CTF_TOOLS_STAGE install_missing: $*" >&2

    if ! apt-get -o DPkg::Lock::Timeout=60 \
        -y --no-upgrade install --no-install-recommends "$@"; then

        echo "CTF_TOOLS_STAGE refresh_package_index" >&2

        apt-get -o Acquire::Retries=2 \
            -o Acquire::http::Timeout=20 \
            -o Acquire::https::Timeout=20 update ||
            fail apt_update "Repository update failed"

        apt-get -o DPkg::Lock::Timeout=60 \
            -o Acquire::Retries=2 \
            -y --no-upgrade install --no-install-recommends "$@" ||
            fail apt_install "Required package installation failed"
    fi
else
    echo "CTF_TOOLS_STAGE packages_already_available" >&2
fi

echo "CTF_TOOLS_STAGE verify_wordlist" >&2

mkdir -p /usr/share/wordlists/dirb ||
    fail wordlist "Cannot create wordlist directory"

if [ ! -r /usr/share/wordlists/dirb/common.txt ]; then
    if [ -r /usr/share/dirb/wordlists/common.txt ]; then
        if [ ! -e /usr/share/wordlists/dirb/common.txt ] &&
           [ ! -L /usr/share/wordlists/dirb/common.txt ]; then
            ln -s /usr/share/dirb/wordlists/common.txt \
                /usr/share/wordlists/dirb/common.txt ||
                fail wordlist "Cannot create wordlist symlink"
        fi
    fi
fi

test -r /usr/share/wordlists/dirb/common.txt ||
    fail wordlist "common.txt unavailable or unreadable"

echo "CTF_TOOLS_STAGE verify_account" >&2

if ! id ctfstudent >/dev/null 2>&1; then
    useradd -m -U -s /bin/bash ctfstudent ||
        fail account "Cannot create ctfstudent"
fi

test "$(id -u ctfstudent)" -gt 0 ||
    fail account "Invalid ctfstudent UID"

test "$(id -g ctfstudent)" -gt 0 ||
    fail account "Invalid ctfstudent GID"

for group in $(id -nG ctfstudent); do
    case "$group" in
        root|sudo|wheel|docker|lxd|incus-admin)
            fail account "ctfstudent belongs to privileged group"
            ;;
    esac
done

echo "CTF_TOOLS_STAGE verify_unprivileged_tools" >&2

runuser -u ctfstudent -- sh -ec '
    command -v nmap >/dev/null
    command -v curl >/dev/null
    command -v gobuster >/dev/null
    command -v ip >/dev/null
    command -v ping >/dev/null

    nmap --version >/dev/null
    curl --version >/dev/null
    gobuster --help >/dev/null
    test -r /usr/share/wordlists/dirb/common.txt
' || fail unprivileged_tools "Tool validation failed as ctfstudent"

echo "CTF_TOOLS_STAGE complete" >&2
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
