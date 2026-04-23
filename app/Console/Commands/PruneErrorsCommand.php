<?php

namespace App\Console\Commands;

use App\Enums\IssueStatus;
use App\Models\Event;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Console\Command;

class PruneErrorsCommand extends Command
{
    protected $signature = 'errors:prune {--resolved-issue-days=90 : Drop resolved issues with no events in this many days}';

    protected $description = 'Prune old events per project retention and remove stale resolved issues.';

    public function handle(): int
    {
        $totalEvents = 0;

        foreach (Project::query()->cursor() as $project) {
            $cutoff = now()->subDays($project->event_retention_days);
            $deleted = Event::query()
                ->where('project_id', $project->id)
                ->where('received_at', '<', $cutoff)
                ->delete();

            if ($deleted > 0) {
                $this->line("[{$project->slug}] pruned {$deleted} events older than {$project->event_retention_days} days");
            }

            $totalEvents += $deleted;
        }

        $staleCutoff = now()->subDays((int) $this->option('resolved-issue-days'));

        $staleIssues = Issue::query()
            ->where('status', IssueStatus::Resolved)
            ->where('last_seen_at', '<', $staleCutoff)
            ->whereDoesntHave('events', function ($q) use ($staleCutoff) {
                $q->where('received_at', '>=', $staleCutoff);
            })
            ->delete();

        $this->info("Pruned {$totalEvents} events and {$staleIssues} stale resolved issues.");

        return self::SUCCESS;
    }
}
