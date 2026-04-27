<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class SetupProject extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.setup-project';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function getTitle(): string
    {
        return 'Connect '.$this->getRecord()->name;
    }

    public function getHeading(): string
    {
        return 'Connect '.$this->getRecord()->name;
    }

    public function getSubheading(): ?string
    {
        return 'Wire your Laravel app to ship errors to this project.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('edit')
                ->label('Edit project')
                ->icon('heroicon-o-pencil-square')
                ->url(fn () => ProjectResource::getUrl('edit', ['record' => $this->getRecord()])),
            Actions\Action::make('back')
                ->label('Back to projects')
                ->color('gray')
                ->url(fn () => ProjectResource::getUrl('index')),
        ];
    }

    public function getViewData(): array
    {
        return [
            'project' => $this->getRecord(),
            'endpoint' => rtrim((string) config('app.url'), '/'),
            'packageName' => 'matthiasvangorp/error-reporter',
            'packagistUrl' => 'https://packagist.org/packages/matthiasvangorp/error-reporter',
        ];
    }
}
