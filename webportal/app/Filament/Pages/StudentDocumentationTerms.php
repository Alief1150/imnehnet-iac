<?php



namespace App\Filament\Pages;



use Filament\Pages\Page;

use Filament\Panel;



// IMNEHNET_STUDENT_HD_V3

class StudentDocumentationTerms extends Page

{

    protected string $view = 'filament.pages.student-documentation-terms';

    protected static ?int $navigationSort = 6;



    public static function getSlug(?Panel $panel = null): string

    {

        return 'documentation/terms';

    }



    public static function getNavigationLabel(): string

    {

        return 'Ketentuan Penggunaan';

    }



    public function getTitle(): string

    {

        return 'Ketentuan Penggunaan';

    }



    public static function canAccess(): bool

    {

        return \Filament\Facades\Filament::getCurrentPanel()?->getId() === 'student'

            && auth()->user()?->isActive() === true;

    }


}

