<?php

namespace App\Filament\Widgets;

use App\Enums\IssueStatus;
use App\Models\Event;
use App\Models\Issue;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpenIssuesOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $projectIds = auth()->user()->accessibleProjectIds();

        $openIssues = Issue::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', IssueStatus::Open)
            ->count();

        $newIssues24h = Issue::query()
            ->whereIn('project_id', $projectIds)
            ->where('first_seen_at', '>=', now()->subDay())
            ->count();

        $events24h = Event::query()
            ->whereIn('project_id', $projectIds)
            ->where('received_at', '>=', now()->subDay())
            ->count();

        $projectCount = $projectIds->count();

        return [
            Stat::make('Open issues', (string) $openIssues)
                ->description($projectCount.' project'.($projectCount === 1 ? '' : 's'))
                ->color('warning'),

            Stat::make('New issues (24h)', (string) $newIssues24h)
                ->description('First-seen in the last 24 hours')
                ->color($newIssues24h > 0 ? 'danger' : 'success'),

            Stat::make('Events (24h)', (string) $events24h)
                ->description('Total events received')
                ->color('primary'),
        ];
    }
}
