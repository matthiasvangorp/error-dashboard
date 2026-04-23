<?php

namespace Tests\Feature;

use App\Enums\IssueStatus;
use App\Jobs\DispatchAlertJob;
use App\Models\Event;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IngestTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create([
            'secret' => 'supersecret',
            'token' => 'testtoken123',
            'rate_limit_per_minute' => 60,
        ]);
    }

    private function sign(string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $body, $this->project->secret);
    }

    private function exceptionPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'type' => 'exception',
            'exception' => [
                'class' => 'RuntimeException',
                'message' => 'User 4242 not found',
                'file' => '/var/www/html/app/Services/Foo.php',
                'line' => 42,
            ],
            'context' => ['environment' => 'production'],
        ], $overrides);
    }

    public function test_valid_ingest_creates_issue_and_event(): void
    {
        Queue::fake();

        $payload = $this->exceptionPayload();
        $body = json_encode($payload);

        $this->postJson("/api/ingest/{$this->project->token}", $payload, [
            'X-Signature' => $this->sign($body),
        ])->assertStatus(202)
            ->assertJsonStructure(['issue_id', 'event_id']);

        $this->assertSame(1, Issue::count());
        $this->assertSame(1, Event::count());

        $issue = Issue::first();
        $this->assertSame(1, $issue->occurrence_count);
        $this->assertSame($this->project->id, $issue->project_id);
        $this->assertSame(IssueStatus::Open, $issue->status);

        Queue::assertPushedOn('alerts', DispatchAlertJob::class);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        Queue::fake();

        $payload = $this->exceptionPayload();

        $this->postJson("/api/ingest/{$this->project->token}", $payload, [
            'X-Signature' => 'sha256=deadbeef',
        ])->assertStatus(401);

        $this->assertSame(0, Issue::count());
        Queue::assertNothingPushed();
    }

    public function test_unknown_project_returns_404(): void
    {
        $this->postJson('/api/ingest/does-not-exist', [], [
            'X-Signature' => 'sha256=x',
        ])->assertStatus(404);
    }

    public function test_duplicate_fingerprint_increments_counter_without_alerting(): void
    {
        Queue::fake();

        // First event creates issue + queues alert.
        $first = $this->exceptionPayload();
        $body1 = json_encode($first);
        $this->postJson("/api/ingest/{$this->project->token}", $first, [
            'X-Signature' => $this->sign($body1),
        ])->assertStatus(202);

        Queue::assertPushed(DispatchAlertJob::class, 1);

        // Second event — different message, same class/file/line → same fingerprint.
        $second = $this->exceptionPayload(['exception' => ['message' => 'User 9999 not found']]);
        $body2 = json_encode($second);
        $this->postJson("/api/ingest/{$this->project->token}", $second, [
            'X-Signature' => $this->sign($body2),
        ])->assertStatus(202);

        $this->assertSame(1, Issue::count());
        $this->assertSame(2, Event::count());
        $this->assertSame(2, Issue::first()->occurrence_count);

        // Still only the original alert — no second push.
        Queue::assertPushed(DispatchAlertJob::class, 1);
    }

    public function test_reopening_a_resolved_issue_triggers_alert(): void
    {
        Queue::fake();

        $payload = $this->exceptionPayload();
        $body = json_encode($payload);

        // Create the issue via a first ingest.
        $this->postJson("/api/ingest/{$this->project->token}", $payload, [
            'X-Signature' => $this->sign($body),
        ])->assertStatus(202);

        Queue::assertPushed(DispatchAlertJob::class, 1);

        // Resolve it.
        Issue::first()->update(['status' => IssueStatus::Resolved]);

        // Same fingerprint arrives again.
        $this->postJson("/api/ingest/{$this->project->token}", $payload, [
            'X-Signature' => $this->sign($body),
        ])->assertStatus(202);

        $this->assertSame(IssueStatus::Open, Issue::first()->status);

        Queue::assertPushed(DispatchAlertJob::class, 2);
        Queue::assertPushed(DispatchAlertJob::class, fn (DispatchAlertJob $job) => $job->reason === 'reopened');
    }

    public function test_rate_limit_enforced_per_project(): void
    {
        $this->project->update(['rate_limit_per_minute' => 2]);

        $payload = $this->exceptionPayload();
        $body = json_encode($payload);
        $sig = $this->sign($body);

        $hits = 0;
        $limited = false;
        for ($i = 0; $i < 5; $i++) {
            $res = $this->postJson("/api/ingest/{$this->project->token}", $payload, ['X-Signature' => $sig]);
            if ($res->status() === 202) {
                $hits++;
            }
            if ($res->status() === 429) {
                $limited = true;
                break;
            }
        }

        $this->assertSame(2, $hits, 'Exactly 2 requests should succeed before being limited');
        $this->assertTrue($limited, 'Rate limiter should kick in after 2 requests');
    }

    public function test_log_events_group_by_templatized_message(): void
    {
        Queue::fake();

        $logPayload = fn (int $id) => [
            'type' => 'log',
            'log' => [
                'channel' => 'single',
                'level' => 'error',
                'message' => "User {$id} not found",
            ],
        ];

        foreach ([1, 2, 3] as $id) {
            $body = json_encode($logPayload($id));
            $this->postJson("/api/ingest/{$this->project->token}", $logPayload($id), [
                'X-Signature' => $this->sign($body),
            ])->assertStatus(202);
        }

        $this->assertSame(1, Issue::count());
        $this->assertSame(3, Event::count());
        $this->assertSame(3, Issue::first()->occurrence_count);
    }
}
