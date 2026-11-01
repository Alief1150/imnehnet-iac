<?php



namespace App\Filament\Pages;



use Filament\Pages\Page;

use Filament\Panel;



// IMNEHNET_STUDENT_HD_V3

class StudentDocumentation extends Page

{

    protected string $view = 'filament.pages.student-documentation';

    protected static ?int $navigationSort = 5;



    public static function getSlug(?Panel $panel = null): string

    {

        return 'documentation';

    }



    public static function getNavigationLabel(): string

    {

        return 'Documentation';

    }



    public function getTitle(): string

    {

        return 'Documentation';

    }



    public static function canAccess(): bool

    {

        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'student'

            && auth()->user()?->isActive() === true;

    }


}

