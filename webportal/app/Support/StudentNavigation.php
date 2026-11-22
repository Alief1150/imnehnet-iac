<?php
namespace App\Support;

/** One source of truth for the entire student navigation (sidebar + user menu). */
final class StudentNavigation
{
    private const BRAND_IMAGE = 'imnehnet-login/branding/logo_imnehnet.webp';

    public static function logoUrl(): string
    {
        return asset(self::BRAND_IMAGE);
    }

    public static function items(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'menu' => 'My Dashboard', 'url' => url('/student'), 'icon' => 'grid', 'active' => request()->is('student')],
            ['key' => 'labs', 'label' => 'My Labs', 'menu' => 'My Labs', 'url' => url('/student/my-labs'), 'icon' => 'server', 'active' => request()->is('student/my-labs*')],
            ['key' => 'pentest', 'label' => 'Pentest', 'menu' => 'Pentest', 'url' => url('/student/guided-ctf'), 'icon' => 'terminal', 'active' => request()->is('student/guided-ctf*')],
            ['key' => 'history', 'label' => 'History', 'menu' => 'History', 'url' => url('/student/history'), 'icon' => 'history', 'active' => request()->is('student/history*')],
            ['key' => 'documentation', 'label' => 'Documentation', 'menu' => 'Documentation', 'url' => url('/student/documentation'), 'icon' => 'book', 'active' => request()->is('student/documentation*')],
            ['key' => 'profile', 'label' => 'Profile', 'menu' => 'My Profile', 'url' => url('/student/profile'), 'icon' => 'user', 'active' => request()->is('student/profile*')],
        ];
    }

    public static function icon(string $id): string
    {
        $path = match ($id) {
        'grid' => '<rect x="3" y="3" width="7" height="8" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="15" width="7" height="6" rx="1"/>',
        'server' => '<rect x="3" y="3" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 6h.01M7 17h.01"/>',
        'terminal' => '<path d="m4 6 6 6-6 6M12 18h8"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'book' => '<path d="M12 7v14M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 13h-5a4 4 0 0 0-4 3 4 4 0 0 0-4-3z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'check' => '<path d="m5 12 4 4 10-10"/>',
        default => '<circle cx="12" cy="12" r="9"/>',
    };
        return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$path.'</svg>';
    }
}
