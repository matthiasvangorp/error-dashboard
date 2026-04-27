<?php

namespace App\Filament\Resources;

use App\Enums\IssueLevel;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Filament\Resources\IssueResource\Pages;
use App\Models\Issue;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
                Tables\Actions\Action::make('createTicket')
                    ->label('Create ticket')
                    ->icon('heroicon-o-ticket')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription('Create a ticket in letsdothis for this issue?')
                    ->visible(fn (Issue $r) => $r->letsdothis_ticket_id === null && $r->project->isLinkedToLetsdothis())
                    ->action(fn (Issue $r) => static::createLetsdothisTicket($r)),
                Tables\Actions\Action::make('viewTicket')
                    ->label(fn (Issue $r) => 'Ticket #'.$r->letsdothis_ticket_id)
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('success')
                    ->visible(fn (Issue $r) => $r->letsdothis_ticket_id !== null && $r->letsdothis_ticket_url !== null)
                    ->url(fn (Issue $r) => $r->letsdothis_ticket_url, shouldOpenInNewTab: true),
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

    protected static function createLetsdothisTicket(Issue $issue): void
    {
        $project = $issue->project;
        $base = rtrim((string) $project->letsdothis_base_url, '/');

        $priority = match ($issue->level instanceof IssueLevel ? $issue->level : IssueLevel::tryFrom((string) $issue->level)) {
            IssueLevel::Error => 'high',
            IssueLevel::Warning => 'medium',
            default => 'low',
        };

        try {
            $response = Http::withToken($project->letsdothis_project_token)
                ->acceptJson()
                ->timeout(15)
                ->post($base.'/api/tickets', [
                    'title' => $issue->title,
                    'description' => static::buildTicketDescription($issue),
                    'priority' => $priority,
                    'external_ref' => 'errors-dashboard:issue:'.$issue->id,
                ]);
        } catch (\Throwable $e) {
            Log::error('Letsdothis ticket creation failed', [
                'issue_id' => $issue->id,
                'error' => $e->getMessage(),
            ]);
            Notification::make()
                ->danger()
                ->title('Could not reach letsdothis')
                ->body($e->getMessage())
                ->send();
            return;
        }

        if (!$response->successful()) {
            Log::warning('Letsdothis ticket creation rejected', [
                'issue_id' => $issue->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            Notification::make()
                ->danger()
                ->title('Letsdothis rejected the request')
                ->body('HTTP '.$response->status().' — '.\Illuminate\Support\Str::limit($response->body(), 200))
                ->send();
            return;
        }

        $payload = $response->json();
        $issue->update([
            'letsdothis_ticket_id' => $payload['id'] ?? null,
            'letsdothis_ticket_url' => $payload['url'] ?? null,
        ]);

        $created = ($payload['status'] ?? null) === 'created';
        Notification::make()
            ->success()
            ->title($created ? 'Ticket created' : 'Linked to existing ticket')
            ->body('Ticket #'.($payload['id'] ?? '?'))
            ->send();
    }

    protected static function buildTicketDescription(Issue $issue): string
    {
        $event = $issue->lastEvent;
        $payload = $event?->payload ?? [];
        $url = static::getUrl('view', ['record' => $issue->id]);
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $level = $issue->level instanceof IssueLevel ? $issue->level->label() : (string) $issue->level;
        $type = $issue->type instanceof IssueType ? $issue->type->label() : (string) $issue->type;

        $rows = [
            ['Project', $issue->project->name],
            ['Environment', $issue->environment ?? '—'],
            ['Level', $level],
            ['Type', $type],
            ['First seen', $issue->first_seen_at?->toDateTimeString()],
            ['Last seen', $issue->last_seen_at?->toDateTimeString()],
            ['Occurrences', (string) $issue->occurrence_count],
        ];

        $html = '<p><a href="'.$e($url).'" target="_blank" rel="noopener">View issue in Error Dashboard ↗</a></p>';
        $html .= '<ul>';
        foreach ($rows as [$label, $value]) {
            $html .= '<li><strong>'.$e($label).':</strong> '.$e($value).'</li>';
        }
        $html .= '</ul>';

        if ($message = $payload['message'] ?? null) {
            $html .= '<p><strong>Message:</strong></p><p>'.nl2br($e($message)).'</p>';
        }

        if ($exception = $payload['exception'] ?? null) {
            $html .= '<p><strong>Exception:</strong> '.$e($exception['class'] ?? '?')
                .' at '.$e($exception['file'] ?? '?').':'.$e($exception['line'] ?? '?').'</p>';

            if (!empty($exception['trace']) && is_array($exception['trace'])) {
                $traceLines = [];
                foreach (array_slice($exception['trace'], 0, 20) as $frame) {
                    $traceLines[] = sprintf(
                        '%s:%s %s',
                        $frame['file'] ?? '?',
                        $frame['line'] ?? '?',
                        $frame['function'] ?? ''
                    );
                }
                $html .= '<pre>'.$e(implode("\n", $traceLines)).'</pre>';
            }
        }

        return $html;
    }
}
