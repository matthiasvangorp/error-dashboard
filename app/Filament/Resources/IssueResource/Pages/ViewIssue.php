<?php

namespace App\Filament\Resources\IssueResource\Pages;

use App\Enums\IssueStatus;
use App\Filament\Resources\IssueResource;
use App\Models\Issue;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewIssue extends ViewRecord
{
    protected static string $resource = IssueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resolve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status !== IssueStatus::Resolved)
                ->action(fn () => $this->record->update(['status' => IssueStatus::Resolved])),
            Actions\Action::make('ignore')
                ->icon('heroicon-o-eye-slash')
                ->color('gray')
                ->visible(fn () => $this->record->status !== IssueStatus::Ignored)
                ->action(fn () => $this->record->update(['status' => IssueStatus::Ignored])),
            Actions\Action::make('reopen')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->status !== IssueStatus::Open)
                ->action(fn () => $this->record->update(['status' => IssueStatus::Open])),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Summary')
                ->columns(4)
                ->schema([
                    TextEntry::make('project.name')->label('Project'),
                    TextEntry::make('level')->badge()
                        ->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('status')->badge()
                        ->formatStateUsing(fn ($state) => $state->label()),
                    TextEntry::make('occurrence_count')->label('Occurrences'),
                    TextEntry::make('first_seen_at')->dateTime(),
                    TextEntry::make('last_seen_at')->dateTime(),
                    TextEntry::make('environment')->default('—'),
                    TextEntry::make('fingerprint')
                        ->copyable()
                        ->formatStateUsing(fn (string $state) => substr($state, 0, 16).'…'),
                ]),

            Section::make('Latest event')
                ->schema([
                    ViewEntry::make('lastEvent')
                        ->view('filament.issue.latest-event'),
                ]),

            Section::make('Recent events')
                ->schema([
                    ViewEntry::make('recentEvents')
                        ->view('filament.issue.recent-events'),
                ]),

            Section::make('Occurrences over time')
                ->schema([
                    ViewEntry::make('occurrenceChart')
                        ->view('filament.issue.occurrence-chart'),
                ]),
        ]);
    }

    protected function resolveRecord(int | string $key): Issue
    {
        return Issue::with(['project', 'lastEvent'])->findOrFail($key);
    }
}
