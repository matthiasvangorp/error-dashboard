<?php

namespace App\Filament\Widgets;

use App\Enums\IssueStatus;
use App\Models\Project;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OpenIssuesByProject extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Open issues by project';

    public function table(Table $table): Table
    {
        return $table
            ->query(Project::query()->withCount([
                'issues as open_issues_count' => fn ($q) => $q->where('status', IssueStatus::Open),
                'issues as new_issues_24h_count' => fn ($q) => $q->where('first_seen_at', '>=', now()->subDay()),
                'events as events_24h_count' => fn ($q) => $q->where('received_at', '>=', now()->subDay()),
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable(),
                Tables\Columns\TextColumn::make('open_issues_count')->label('Open')->sortable(),
                Tables\Columns\TextColumn::make('new_issues_24h_count')->label('New 24h')->sortable(),
                Tables\Columns\TextColumn::make('events_24h_count')->label('Events 24h')->sortable(),
            ])
            ->defaultSort('open_issues_count', 'desc')
            ->paginated(false);
    }
}
