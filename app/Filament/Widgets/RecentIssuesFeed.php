<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\IssueResource;
use App\Models\Issue;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentIssuesFeed extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Recently seen';

    public function table(Table $table): Table
    {
        $projectIds = auth()->user()->accessibleProjectIds();

        return $table
            ->query(Issue::query()
                ->whereIn('project_id', $projectIds)
                ->with('project')
                ->latest('last_seen_at')
                ->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('project.name')->label('Project')->badge(),
                Tables\Columns\TextColumn::make('title')->limit(70)->wrap(),
                Tables\Columns\TextColumn::make('level')->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
                Tables\Columns\TextColumn::make('occurrence_count')->label('Count')->sortable(),
                Tables\Columns\TextColumn::make('last_seen_at')->since()->sortable(),
            ])
            ->paginated(false)
            ->recordUrl(fn (Issue $r) => IssueResource::getUrl('view', ['record' => $r]));
    }
}
