<?php

namespace App\Alerting\Channels;

use App\Alerting\AlertChannel;
use App\Filament\Resources\IssueResource;
use App\Models\Event;
use App\Models\Issue;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramChannel implements AlertChannel
{
    public function __construct(
        private readonly ?string $botToken,
        private readonly ?string $defaultChatId = null,
    ) {
    }

    public function name(): string
    {
        return 'telegram';
    }

    public function send(Issue $issue, Event $event, string $reason): void
    {
        $chatId = $this->chatIdFor($issue);

        if (! $chatId) {
            return;
        }

        if (! $this->botToken) {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not configured.');
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

    /**
     * Project-level chat_id wins; otherwise fall back to TELEGRAM_CHAT_ID.
     */
    private function chatIdFor(Issue $issue): ?string
    {
        $config = (array) ($issue->project->alert_channels['telegram'] ?? []);
        $chatId = $config['chat_id'] ?? null;

        $chatId = is_string($chatId) || is_int($chatId) ? trim((string) $chatId) : '';

        return $chatId !== '' ? $chatId : ($this->defaultChatId ?: null);
    }

    private function renderMessage(Issue $issue, Event $event, string $reason): string
    {
        $project = $issue->project;
        $badge = $reason === 'reopened' ? 'REOPENED' : 'NEW';
        $title = htmlspecialchars($issue->title, ENT_QUOTES, 'UTF-8');
        $projectName = htmlspecialchars($project->name, ENT_QUOTES, 'UTF-8');
        $env = $event->environment ? " [{$event->environment}]" : '';
        $url = IssueResource::getUrl('view', ['record' => $issue->id], panel: 'admin');

        return <<<TXT
<b>[{$badge}]</b> {$projectName}{$env}
{$title}

First seen: {$issue->first_seen_at->toDateTimeString()}
<a href="{$url}">View issue</a>
TXT;
    }
}
