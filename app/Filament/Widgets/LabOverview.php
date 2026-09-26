<?php

namespace App\Filament\Widgets;

use App\Models\Lab;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class LabOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Control Plane Overview';

    protected function getStats(): array
    {
        return [
            Stat::make('Student Accounts', User::query()->where('role', 'student')->count()),
            Stat::make('Students Without Lab', User::query()->where('role', 'student')->doesntHave('lab')->count())
                ->color('warning'),
            Stat::make('Portal Lab Records', Lab::query()->count())
                ->description('Database records; not an Incus instance count.'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }
}
