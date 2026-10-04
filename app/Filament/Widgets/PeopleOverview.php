<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * How many people use this portal, and how they arrived.
 *
 * This answers the question an operator actually asks once students start
 * registering: how many are there, how many are students, how many are disabled,
 * and how many are new. Nothing on the Environments page answers any of those,
 * because a student who has registered but never created a lab is invisible
 * there.
 *
 * Administrators only. The size of the cohort is not a student's business, and
 * the account list is not something a student should be able to enumerate.
 */
final class PeopleOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'People';

    protected function getColumns(): int
    {
        return 5;
    }

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    protected function getStats(): array
    {
        $total = User::query()->count();

        $students = User::query()
            ->where('role', UserRole::Student->value)
            ->count();

        $administrators = User::query()
            ->where('role', UserRole::Admin->value)
            ->count();

        $disabled = User::query()
            ->where('status', UserStatus::Disabled->value)
            ->count();

        // Seven days, because the question is "is anyone new still arriving",
        // which is a question about arrival rather than a total that only grows.
        $recent = User::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            Stat::make('Accounts', $total)
                ->description(sprintf('%d student, %d administrator', $students, $administrators))
                ->descriptionIcon('heroicon-o-users')
                ->color('gray'),

            Stat::make('Students', $students)
                ->description('Can provision their own environments')
                ->descriptionIcon('heroicon-o-academic-cap')
                ->color($students > 0 ? 'success' : 'warning'),

            Stat::make('Administrators', $administrators)
                // One administrator can lock everyone out if their account is
                // deleted, and the refusal lives in the Users page. This only
                // says whether the count is worth looking at.
                ->description($administrators === 1 ? 'Only one: nothing to fall back to' : 'Can manage everyone')
                ->descriptionIcon('heroicon-o-shield-check')
                ->color($administrators === 1 ? 'warning' : 'gray'),

            Stat::make('Disabled', $disabled)
                ->description($disabled > 0 ? 'Cannot sign in' : 'None')
                ->descriptionIcon($disabled > 0 ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                ->color($disabled > 0 ? 'danger' : 'gray'),

            Stat::make('New in 7 days', $recent)
                ->description('Registered recently')
                ->descriptionIcon('heroicon-o-sparkles')
                ->color('gray'),
        ];
    }
}