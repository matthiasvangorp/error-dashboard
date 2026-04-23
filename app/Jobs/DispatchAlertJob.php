<?php

namespace App\Jobs;

use App\Alerting\AlertManager;
use App\Models\Event;
use App\Models\Issue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchAlertJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $issueId,
        public int $eventId,
        public string $reason,
    ) {
    }

    public function handle(AlertManager $alerts): void
    {
        $issue = Issue::with('project')->find($this->issueId);
        $event = Event::find($this->eventId);

        if (! $issue || ! $event) {
            return;
        }

        $alerts->send($issue, $event, $this->reason);
    }
}
