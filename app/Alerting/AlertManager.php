<?php

namespace App\Alerting;

use App\Models\Event;
use App\Models\Issue;
use Illuminate\Support\Facades\Log;

class AlertManager
{
    /**
     * @param  array<int, AlertChannel>  $channels
     */
    public function __construct(private readonly array $channels = [])
    {
    }

    public function send(Issue $issue, Event $event, string $reason): void
    {
        $project = $issue->project;
        $config = (array) ($project->alert_channels ?? []);

        foreach ($this->channels as $channel) {
            if (! array_key_exists($channel->name(), $config)) {
                continue;
            }

            try {
                $channel->send($issue, $event, $reason);
            } catch (\Throwable $e) {
                Log::warning('Alert channel failed', [
                    'channel' => $channel->name(),
                    'issue_id' => $issue->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
