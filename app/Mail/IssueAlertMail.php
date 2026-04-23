<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Issue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IssueAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Issue $issue,
        public Event $event,
        public string $reason,
    ) {
    }

    public function envelope(): Envelope
    {
        $badge = $this->reason === 'reopened' ? 'Reopened' : 'New';
        $project = $this->issue->project->name;

        return new Envelope(
            subject: "[{$badge}] {$project} — ".$this->issue->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.issue-alert',
            with: [
                'issue' => $this->issue,
                'event' => $this->event,
                'reason' => $this->reason,
                'url' => rtrim(config('app.url'), '/').'/admin/issues/'.$this->issue->id,
            ],
        );
    }
}
