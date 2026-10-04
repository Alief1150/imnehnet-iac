<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\StudentPanelProvider;
use App\Providers\IconServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    StudentPanelProvider::class,
    IconServiceProvider::class,
];
