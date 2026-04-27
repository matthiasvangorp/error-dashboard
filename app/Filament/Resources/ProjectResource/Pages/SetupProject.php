<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Project;
use Filament\Actions;
use Filament\Resources\Pages\Page;

class SetupProject extends Page
{
    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.setup-project';

    public Project $record;

    public function mount(int|string $record): void
    {
        $this->record = static::getResource()::resolveRecordRouteBinding($record);
        abort_unless($this->record !== null, 404);
        static::authorizeResourceAccess();
    }

    public function getTitle(): string
    {
        return 'Connect '.$this->record->name;
    }

    public function getHeading(): string
    {
        return 'Connect '.$this->record->name;
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
                ->url(fn () => ProjectResource::getUrl('edit', ['record' => $this->record])),
            Actions\Action::make('back')
                ->label('Back to projects')
                ->color('gray')
                ->url(fn () => ProjectResource::getUrl('index')),
        ];
    }

    public function getViewData(): array
    {
        return [
            'project' => $this->record,
            'endpoint' => rtrim((string) config('app.url'), '/'),
            'packageName' => 'matthiasvangorp/error-reporter',
            'packagistUrl' => 'https://packagist.org/packages/matthiasvangorp/error-reporter',
        ];
    }
}
