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

        // Claim the alert atomically: only the first job to flip alerted_at
        // from NULL gets to send. Retries or duplicate dispatches are no-ops.
        $claimed = Issue::query()
            ->whereKey($issue->id)
            ->whereNull('alerted_at')
            ->update(['alerted_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $alerts->send($issue, $event, $this->reason);
    }
}
