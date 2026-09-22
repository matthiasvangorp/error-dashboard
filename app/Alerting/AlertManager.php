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

    /**
     * Fan out to every channel. Each channel decides for itself whether it is
     * configured for the project (or globally) and returns silently if not.
     */
    public function send(Issue $issue, Event $event, string $reason): void
    {
        foreach ($this->channels as $channel) {
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
