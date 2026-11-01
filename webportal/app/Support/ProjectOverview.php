<?php

namespace App\Support;

use App\Services\SettingsService;
use Composer\InstalledVersions;

/** Editable documentation, never infrastructure configuration or a health probe. */
final class ProjectOverview
{
    public const KEY = 'imnehnet_project_overview_v1';

    public static function defaults(): array
    {
        $software = [
            ['PHP', 'Portal', 'Runtime aplikasi portal.'],
            ['Laravel', 'Portal', 'Framework aplikasi dan backend.'],
            ['Filament', 'Portal', 'Panel dan komponen administrasi.'],
            ['Linux Mint', 'Virtualisasi / Lab', 'Sistem operasi physical host.'],
            ['KVM/QEMU', 'Virtualisasi / Lab', 'Virtualisasi VM utama.'],
            ['libvirt', 'Virtualisasi / Lab', 'Manajemen lifecycle mesin virtual.'],
            ['Incus', 'Virtualisasi / Lab', 'System container attacker dan defender.'],
            ['OpenTofu', 'Automation / Data', 'Provisioning dan lifecycle resource secara deklaratif.'],
            ['Ansible', 'Automation / Data', 'Konfigurasi OS, package, user, dan policy setelah provisioning.'],
            ['MySQL', 'Automation / Data', 'Penyimpanan data aplikasi.'],
            ['Wazuh', 'Monitoring', 'Pengumpulan log dan monitoring security event.'],
            ['Prometheus', 'Monitoring', 'Pengumpulan metrik resource.'],
            ['Grafana', 'Monitoring', 'Visualisasi metrik dan performa.'],
            ['nftables', 'Security / Connectivity', 'Pembatasan komunikasi dengan kebijakan default-deny.'],
            ['Incus ACL', 'Security / Connectivity', 'Kontrol akses antar-environment praktikum.'],
            ['FlyonUI', 'Portal', 'Komponen antarmuka berbasis Tailwind CSS.'],
            ['Tailwind CSS', 'Portal', 'Styling dan layout responsif.'],
            ['Livewire', 'Portal', 'Interaksi formulir dan halaman berbasis PHP.'],
        ];

        return [
            'name' => 'IMNEHNET — Lab as a Service',
            'release' => '',
            'summary' => 'IMNEHNET adalah platform Lab as a Service (LaaS) untuk praktikum keamanan siber. Mahasiswa mengelola lingkungan attacker dan defender melalui portal terpusat, dengan isolasi antar-pengguna dan pembatasan akses ke sistem manajemen.',
            'objectives' => 'Menyediakan praktikum yang terisolasi, terpusat, dan dapat diulang. Mengotomatisasi penyediaan environment serta mengevaluasi keamanan, waktu provisioning, penggunaan resource, dan skalabilitas.',
            'architecture' => 'Satu server fisik menjalankan Linux Mint dengan KVM/QEMU dan libvirt. Tiga VM utama adalah IAAS-MGMT (portal, backend, database, dan automation), LAB-ENV (Incus defender), serta LAB-ATK (Incus attacker). OpenTofu menyediakan resource dan Ansible mengonfigurasinya. Incus ACL dan nftables membatasi komunikasi. Wazuh memantau keamanan; Prometheus dan Grafana memantau performa.',
            'features' => 'Create, Start, Stop, Reset, dan Delete environment; pasangan attacker–defender; micro-segmentation; automation; monitoring keamanan dan resource; Guided CTF dengan terminal dan validasi objective.',
            'team' => "Abdilah Ramdhani — Cloud Architect\nAlief Athallah Putra — DevOps Engineer\nMuhammad Ridho Rifai — Security Engineer\nSeptihadi Malik — Portal Developer",
            'institution' => 'Teknik Multimedia Jaringan · TIK · Politeknik Negeri Jakarta',
            'software' => array_map(fn (array $row): array => [
                'name' => $row[0], 'group' => $row[1], 'purpose' => $row[2], 'version' => match ($row[0]) {
                    'PHP' => PHP_VERSION,
                    'Laravel' => app()->version(),
                    'Filament' => InstalledVersions::isInstalled('filament/filament') ? (InstalledVersions::getPrettyVersion('filament/filament') ?? '') : '',
                    'Livewire' => InstalledVersions::isInstalled('livewire/livewire') ? (InstalledVersions::getPrettyVersion('livewire/livewire') ?? '') : '',
                    default => '',
                },
            ], $software),
        ];
    }

    public static function read(): array
    {
        $raw = app(SettingsService::class)->get(self::KEY);
        $saved = is_string($raw) ? json_decode($raw, true) : null;

        return array_replace(self::defaults(), is_array($saved) ? $saved : []);
    }

    public static function write(array $data): void
    {
        app(SettingsService::class)->put(self::KEY, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
