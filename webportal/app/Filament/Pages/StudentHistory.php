<?php



namespace App\Filament\Pages;



use Filament\Pages\Page;

use Filament\Panel;



// IMNEHNET_STUDENT_HD_V3

class StudentHistory extends Page

{

    protected string $view = 'filament.pages.student-history';

    protected static ?int $navigationSort = 4;



    public static function getSlug(?Panel $panel = null): string

    {

        return 'history';

    }



    public static function getNavigationLabel(): string

    {

        return 'History';

    }



    public function getTitle(): string

    {

        return 'History';

    }



    public static function canAccess(): bool

    {

        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'student'

            && auth()->user()?->isActive() === true;

    }


}

