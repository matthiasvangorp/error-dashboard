<?php

namespace App\Alerting;

use App\Models\Event;
use App\Models\Issue;

interface AlertChannel
{
    public function name(): string;

    public function send(Issue $issue, Event $event, string $reason): void;
}
