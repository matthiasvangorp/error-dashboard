<?php

namespace App\Alerting\Channels;

use App\Alerting\AlertChannel;
use App\Mail\IssueAlertMail;
use App\Models\Event;
use App\Models\Issue;
use Illuminate\Support\Facades\Mail;

class MailChannel implements AlertChannel
{
    public function name(): string
    {
        return 'email';
    }

    public function send(Issue $issue, Event $event, string $reason): void
    {
        $recipients = array_values(array_filter(
            (array) ($issue->project->alert_channels['email'] ?? []),
            static fn ($addr) => is_string($addr) && $addr !== '',
        ));

        if ($recipients === []) {
            return;
        }

        Mail::to($recipients)->send(new IssueAlertMail($issue, $event, $reason));
    }
}
