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
    return redirect()->route('filament.admin.auth.login');
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
