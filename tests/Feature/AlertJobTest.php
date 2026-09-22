<?php

namespace Tests\Feature;

use App\Jobs\DispatchAlertJob;
use App\Models\Event;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlertJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeIssue(array $projectAttributes = []): Issue
    {
        $project = Project::factory()->create($projectAttributes);

        $issue = Issue::query()->create([
            'project_id' => $project->id,
            'fingerprint' => str_repeat('a', 64),
            'title' => 'RuntimeException: boom',
            'type' => 'exception',
            'level' => 'error',
            'status' => 'open',
            'environment' => 'production',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'occurrence_count' => 1,
        ]);

        $event = Event::query()->create([
            'issue_id' => $issue->id,
            'project_id' => $project->id,
            'payload' => ['type' => 'exception'],
            'environment' => 'production',
            'received_at' => now(),
        ]);

        $issue->update(['last_event_id' => $event->id]);

        return $issue->fresh();
    }

    public function test_sends_telegram_message_using_default_chat_id(): void
    {
        config()->set('services.telegram.bot_token', 'token123');
        config()->set('services.telegram.chat_id', '4242');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $issue = $this->makeIssue();

        (new DispatchAlertJob($issue->id, $issue->last_event_id, 'new'))->handle(app(\App\Alerting\AlertManager::class));

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($issue) {
            return str_contains($request->url(), 'bottoken123/sendMessage')
                && $request['chat_id'] === '4242'
                && str_contains($request['text'], 'RuntimeException: boom')
                && str_contains($request['text'], '/issues/'.$issue->id);
        });

        $this->assertNotNull($issue->fresh()->alerted_at);
    }

    public function test_project_chat_id_overrides_default(): void
    {
        config()->set('services.telegram.bot_token', 'token123');
        config()->set('services.telegram.chat_id', '4242');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $issue = $this->makeIssue(['alert_channels' => ['telegram' => ['chat_id' => '9999']]]);

        (new DispatchAlertJob($issue->id, $issue->last_event_id, 'new'))->handle(app(\App\Alerting\AlertManager::class));

        Http::assertSent(fn (Request $request) => $request['chat_id'] === '9999');
    }

    public function test_alert_is_sent_at_most_once_per_issue(): void
    {
        config()->set('services.telegram.bot_token', 'token123');
        config()->set('services.telegram.chat_id', '4242');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $issue = $this->makeIssue();
        $manager = app(\App\Alerting\AlertManager::class);

        (new DispatchAlertJob($issue->id, $issue->last_event_id, 'new'))->handle($manager);
        (new DispatchAlertJob($issue->id, $issue->last_event_id, 'new'))->handle($manager);

        Http::assertSentCount(1);
    }

    public function test_nothing_is_sent_without_any_chat_id(): void
    {
        config()->set('services.telegram.bot_token', 'token123');
        config()->set('services.telegram.chat_id', null);
        Http::fake();

        $issue = $this->makeIssue();

        (new DispatchAlertJob($issue->id, $issue->last_event_id, 'new'))->handle(app(\App\Alerting\AlertManager::class));

        Http::assertNothingSent();
    }
}
