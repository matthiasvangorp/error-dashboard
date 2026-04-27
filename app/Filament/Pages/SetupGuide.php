<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class SetupGuide extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $title = 'Setup Guide';

    protected static ?string $navigationLabel = 'Setup Guide';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.setup-guide';

    public function getViewData(): array
    {
        return [
            'endpoint' => rtrim((string) config('app.url'), '/'),
            'packageName' => 'matthiasvangorp/error-reporter',
            'packagistUrl' => 'https://packagist.org/packages/matthiasvangorp/error-reporter',
        ];
    }
}
