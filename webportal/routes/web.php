<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/student/login');
});

// Named login so the landing page and the registration form have one address to
// point at, rather than each hard-coding the panel path. The panel is the only
// thing that authenticates; this is a redirect to it.
Route::get('/login', function () {
    return redirect()->route('filament.student.auth.login'); // IMNEHNET_AUTH_V6
})->name('login');

// Self-registration. Public on purpose: that is the point of it. What bounds it
// is the per-student environment quota, the role being assigned server-side, and
// the rate limit inside the component.
// A view, not the component class: pointing the route straight at the Livewire
// class makes Livewire look for a layout named "layouts", which does not exist.
Route::view('/login/regist', 'imnehnet.auth.register')->name('register');

// Compatibility route for the original Filament Student Dashboard name.
// Redirects legacy links to the current Student Dashboard.
Route::redirect(
    '/student/dashboard-legacy',
    '/student'
)->name('filament.student.pages.dashboard');

// Unduhan berkas ekspor log aktivitas (admin only).
Route::get('/admin/activity-logs/download', function (Request $request) {
    abort_unless(auth()->user()?->isAdmin() === true, 403);

    $fileName = $request->query('file');
    if (! $fileName || ! preg_match('/^imnehnet-activity-logs-[0-9]{8}-[0-9]{6}-[0-9a-f]+\.(xlsx|csv)$/', $fileName)) {
        abort(400, 'Nama berkas ekspor tidak valid.');
    }

    $possiblePaths = [
        storage_path('app/exports/activity-logs/'.$fileName),
        sys_get_temp_dir().'/imnehnet-exports/activity-logs/'.$fileName,
    ];

    $filePath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $filePath = $path;
            break;
        }
    }

    // Fallback: cek session jika file belum ada di path langsung.
    if ($filePath === null) {
        $sessionPath = session('activity_log_export_file');
        if ($sessionPath && basename($sessionPath) === $fileName && file_exists($sessionPath)) {
            $filePath = $sessionPath;
        }
    }

    if ($filePath === null) {
        abort(404, 'Berkas ekspor tidak ditemukan.');
    }

    return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
})->name('activity-logs.download');


// IMNEHNET_ADMIN_STANDALONE_PAIR_TERMINAL_V1
\Illuminate\Support\Facades\Route::middleware(['auth'])
    ->prefix('admin/standalone-pair')
    ->name('admin.standalone-pair.')
    ->group(function () {
        \Illuminate\Support\Facades\Route::get('{environment}', [\App\Http\Controllers\AdminStandalonePairController::class, 'show'])
            ->whereNumber('environment')->name('show');
        \Illuminate\Support\Facades\Route::post('{environment}/open', [\App\Http\Controllers\AdminStandalonePairController::class, 'open'])
            ->whereNumber('environment')->name('open');
        \Illuminate\Support\Facades\Route::post('{environment}/close', [\App\Http\Controllers\AdminStandalonePairController::class, 'close'])
            ->whereNumber('environment')->name('close');
    });

// IMNEHNET_PAIR_UI_STAGE_C_V2
\Illuminate\Support\Facades\Route::middleware(['auth'])
    ->prefix('admin/environment-pair')
    ->name('admin.environment-pair.')
    ->group(function () {
        \Illuminate\Support\Facades\Route::get(
            '{environment}',
            [\App\Http\Controllers\AdminEnvironmentPairController::class, 'show']
        )->whereNumber('environment')->name('show');

        \Illuminate\Support\Facades\Route::post(
            '{environment}/open',
            [\App\Http\Controllers\AdminEnvironmentPairController::class, 'open']
        )->whereNumber('environment')->name('open');

        \Illuminate\Support\Facades\Route::post(
            '{environment}/close',
            [\App\Http\Controllers\AdminEnvironmentPairController::class, 'close']
        )->whereNumber('environment')->name('close');
    });

// Authenticated heartbeat; does not expose user identities.
Route::post('/portal/presence', function (Request $request) {
    abort_unless($request->user()?->isActive() === true, 403);
    \App\Support\UserPresence::heartbeat((int)$request->user()->getKey());
    return response()->noContent();
})->middleware('auth')->name('portal.presence');

// IMNEHNET_STUDENT_VNEXT_20261122: Student notification inbox, account-scoped.
\Illuminate\Support\Facades\Route::middleware(['auth', 'throttle:60,1'])->prefix('student/ui-notifications')->group(function () {
    $studentGuard = static function (): \App\Models\User {
        $user = auth()->user();
        abort_unless($user && $user->isActive() && ! $user->isAdmin(), 403);
        return $user;
    };
    \Illuminate\Support\Facades\Route::get('/', function () use ($studentGuard) {
        $user = $studentGuard();
        $items = $user->notifications()->latest()->limit(20)->get()->map(function ($n) {
            $data = is_array($n->data) ? $n->data : [];
            $url = (string) ($data['action_url'] ?? '/student/my-labs');
            // Never allow a notification payload to redirect outside the Student area.
            if (! str_starts_with($url, '/student/') && $url !== '/student') {
                $path = parse_url($url, PHP_URL_PATH);
                $url = is_string($path) && str_starts_with($path, '/student/') ? $path : '/student/my-labs';
            }
            return [
                'id' => (string) $n->id,
                'title' => (string) ($data['title'] ?? 'IMNEHNET Notification'),
                'message' => (string) ($data['message'] ?? $data['body'] ?? ''),
                'url' => $url,
                'when' => optional($n->created_at)->diffForHumans() ?? '',
                'read' => $n->read_at !== null,
            ];
        });
        return response()->json(['unread' => $user->unreadNotifications()->count(), 'notifications' => $items]);
    });
    \Illuminate\Support\Facades\Route::post('/read-all', function () use ($studentGuard) {
        $studentGuard()->unreadNotifications()->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    });
    \Illuminate\Support\Facades\Route::post('/{id}/read', function (string $id) use ($studentGuard) {
        $studentGuard()->unreadNotifications()->whereKey($id)->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    })->whereUuid('id');
});
