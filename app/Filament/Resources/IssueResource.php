<?php

namespace App\Filament\Resources;

use App\Enums\IssueLevel;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Filament\Resources\IssueResource\Pages;
use App\Models\Issue;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class IssueResource extends Resource
{
    protected static ?string $model = Issue::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('project');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_seen_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Project')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(80)
                    ->wrap(),
                Tables\Columns\TextColumn::make('level')
                    ->badge()
                    ->colors([
                        'danger' => IssueLevel::Error->value,
                        'warning' => IssueLevel::Warning->value,
                        'info' => IssueLevel::Info->value,
                        'gray' => IssueLevel::Debug->value,
                    ])
                    ->formatStateUsing(fn ($state) => $state instanceof IssueLevel ? $state->label() : ucfirst((string) $state)),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => IssueStatus::Open->value,
                        'success' => IssueStatus::Resolved->value,
                        'gray' => IssueStatus::Ignored->value,
                    ])
                    ->formatStateUsing(fn ($state) => $state instanceof IssueStatus ? $state->label() : ucfirst((string) $state)),
                Tables\Columns\TextColumn::make('environment')->toggleable(),
                Tables\Columns\TextColumn::make('occurrence_count')
                    ->label('Count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_seen_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_seen_at')->label('Last seen')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project')
                    ->relationship('project', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(IssueStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all())
                    ->default(IssueStatus::Open->value),
                Tables\Filters\SelectFilter::make('level')
                    ->options(collect(IssueLevel::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()),
                Tables\Filters\SelectFilter::make('type')
                    ->options(collect(IssueType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()),
                Tables\Filters\SelectFilter::make('environment')
                    ->options(fn () => Issue::query()->whereNotNull('environment')->distinct()->pluck('environment', 'environment')->all()),
            ])
            ->groups([
                Tables\Grouping\Group::make('project.name')->label('Project'),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Issue $r) => $r->status !== IssueStatus::Resolved)
                    ->action(fn (Issue $r) => $r->update(['status' => IssueStatus::Resolved])),
                Tables\Actions\Action::make('ignore')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->visible(fn (Issue $r) => $r->status !== IssueStatus::Ignored)
                    ->action(fn (Issue $r) => $r->update(['status' => IssueStatus::Ignored])),
                Tables\Actions\Action::make('reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Issue $r) => $r->status !== IssueStatus::Open)
                    ->action(fn (Issue $r) => $r->update(['status' => IssueStatus::Open])),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('resolve')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn ($records) => $records->each->update(['status' => IssueStatus::Resolved])),
                    Tables\Actions\BulkAction::make('ignore')
                        ->icon('heroicon-o-eye-slash')
                        ->action(fn ($records) => $records->each->update(['status' => IssueStatus::Ignored])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIssues::route('/'),
            'view' => Pages\ViewIssue::route('/{record}'),
        ];
    }
}
