<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class HowItWorks extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-information-circle';

    protected static ?string $title = 'How it works';

    protected static ?string $navigationLabel = 'How it works';

    protected static ?int $navigationSort = 98;

    protected static string $view = 'filament.pages.how-it-works';

    public function getViewData(): array
    {
        return [
            'endpoint' => rtrim((string) config('app.url'), '/'),
        ];
    }
}
