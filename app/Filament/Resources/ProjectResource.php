<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identity')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->alphaDash()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                ])->columns(2),

            Forms\Components\Section::make('Credentials')
                ->description('Token is the path segment; secret is the HMAC signing key. Regenerate via the action on the list.')
                ->schema([
                    Forms\Components\TextInput::make('token')
                        ->required()
                        ->maxLength(64)
                        ->default(fn () => Str::random(32))
                        ->disabled(fn (?Project $record) => $record !== null)
                        ->dehydrated(),
                    Forms\Components\TextInput::make('secret')
                        ->required(fn (string $operation) => $operation === 'create')
                        ->maxLength(255)
                        ->default(fn () => Str::random(48))
                        ->password()
                        ->revealable()
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText(fn (string $operation) => $operation === 'edit'
                            ? 'Leave empty to keep the current secret.'
                            : null),
                ])->columns(2),

            Forms\Components\Section::make('Retention & rate limits')
                ->schema([
                    Forms\Components\TextInput::make('event_retention_days')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->default(30),
                    Forms\Components\TextInput::make('rate_limit_per_minute')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->default(60),
                ])->columns(2),

            Forms\Components\Section::make('Alert channels')
                ->description('Per-project channel config. Leave empty to disable alerts.')
                ->schema([
                    Forms\Components\Fieldset::make('Telegram')
                        ->schema([
                            Forms\Components\TextInput::make('alert_channels.telegram.chat_id')
                                ->label('Chat ID')
                                ->nullable(),
                        ]),
                    Forms\Components\TagsInput::make('alert_channels.email')
                        ->label('Email recipients')
                        ->placeholder('ops@example.com')
                        ->nullable(),
                ]),

            Forms\Components\Section::make('letsdothis link')
                ->description('When set, an issue can be turned into a ticket in this letsdothis project with one click.')
                ->schema([
                    Forms\Components\TextInput::make('letsdothis_base_url')
                        ->label('Base URL')
                        ->url()
                        ->placeholder('https://letsdothis.live')
                        ->nullable(),
                    Forms\Components\TextInput::make('letsdothis_project_token')
                        ->label('Project API token')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('Paste from the linked letsdothis project (External API Access). Leave empty to keep the current token.')
                        ->nullable(),
                ])->columns(2)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('token')
                    ->label('Token')
                    ->formatStateUsing(fn (string $state) => Str::limit($state, 12))
                    ->copyable()
                    ->copyableState(fn (string $state) => $state),
                Tables\Columns\TextColumn::make('issues_count')
                    ->label('Open issues')
                    ->counts([
                        'issues' => fn ($q) => $q->where('status', 'open'),
                    ]),
                Tables\Columns\TextColumn::make('event_retention_days')->label('Retention')
                    ->suffix(' d')->toggleable(),
                Tables\Columns\TextColumn::make('rate_limit_per_minute')->label('Rate limit')
                    ->suffix(' /min')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('setup')
                    ->label('Setup')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('primary')
                    ->url(fn (Project $record) => static::getUrl('setup', ['record' => $record])),
                Tables\Actions\Action::make('regenerateSecret')
                    ->label('Regenerate secret')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This invalidates the current signing key. Clients must be updated with the new secret.')
                    ->action(function (Project $record): void {
                        $record->update(['secret' => Str::random(48)]);
                        Notification::make()
                            ->success()
                            ->title('Secret regenerated')
                            ->body('New secret: '.$record->secret)
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
            'setup' => Pages\SetupProject::route('/{record}/setup'),
        ];
    }
}
