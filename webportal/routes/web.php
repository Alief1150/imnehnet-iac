<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
Route::view('/login/regist', 'register')->name('register');
