<?php

namespace App\Alerting\Channels;

use App\Alerting\AlertChannel;
use App\Models\Event;
use App\Models\Issue;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramChannel implements AlertChannel
{
    public function __construct(private readonly ?string $botToken)
    {
    }

    public function name(): string
    {
        return 'telegram';
    }

    public function send(Issue $issue, Event $event, string $reason): void
    {
        if (! $this->botToken) {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not configured.');
        }

        $project = $issue->project;
        $config = (array) ($project->alert_channels['telegram'] ?? []);
        $chatId = $config['chat_id'] ?? null;

        if (! $chatId) {
            return;
        }

        $text = $this->renderMessage($issue, $event, $reason);

        $response = Http::asJson()
            ->timeout(8)
            ->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Telegram API error: '.$response->status().' '.$response->body());
        }
    }

    private function renderMessage(Issue $issue, Event $event, string $reason): string
    {
        $project = $issue->project;
        $badge = $reason === 'reopened' ? 'REOPENED' : 'NEW';
        $title = htmlspecialchars($issue->title, ENT_QUOTES, 'UTF-8');
        $projectName = htmlspecialchars($project->name, ENT_QUOTES, 'UTF-8');
        $env = $event->environment ? " [{$event->environment}]" : '';
        $url = rtrim(config('app.url'), '/').'/admin/issues/'.$issue->id;

        return <<<TXT
<b>[{$badge}]</b> {$projectName}{$env}
{$title}

First seen: {$issue->first_seen_at->toDateTimeString()}
<a href="{$url}">View issue</a>
TXT;
    }
}
