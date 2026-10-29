<?php

namespace Tests\Feature;

use App\Enums\IssueStatus;
use App\Jobs\ForwardErrorToWebhookJob;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ErrorWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://hooks.example.com/errors';

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.error_webhook.url', self::WEBHOOK);
        config()->set('services.error_webhook.auth', 'Bearer s3cr3t-webhook');
        config()->set('services.error_webhook.projects', null);
        // Keep Telegram out of the way.
        config()->set('services.telegram.chat_id', null);

        $this->project = Project::factory()->create([
            'name' => 'Products That Matter',
            'slug' => 'products-that-matter',
            'secret' => 'supersecret',
            'token' => 'testtoken123',
        ]);

        Http::fake([self::WEBHOOK => Http::response(['ok' => true])]);
    }

    private function ingest(Project $project, array $overrides = []): void
    {
        $payload = array_replace_recursive([
            'type' => 'exception',
            'timestamp' => '2026-10-29T10:00:00+00:00',
            'exception' => [
                'class' => 'RuntimeException',
                'message' => 'Login failed for password=hunter2',
                'file' => '/var/www/html/app/Services/Foo.php',
                'line' => 42,
                'trace' => [
                    ['file' => '/var/www/html/app/Http/Controllers/FooController.php', 'line' => 17, 'class' => 'App\\Services\\Foo', 'type' => '->', 'function' => 'run', 'args' => ['hunter2']],
                ],
            ],
            'context' => [
                'environment' => 'production',
                'url' => 'https://shop.test/checkout?step=2&api_token=abc123',
                'method' => 'post',
                'headers' => ['cookie' => 'laravel_session=xyz', 'authorization' => 'Bearer leaked'],
                'cookies' => ['laravel_session' => 'xyz'],
                'env' => ['DB_PASSWORD' => 'dbpass'],
            ],
        ], $overrides);

        $body = json_encode($payload);

        $this->postJson("/api/ingest/{$project->token}", $payload, [
            'X-Signature' => 'sha256='.hash_hmac('sha256', $body, $project->secret),
        ])->assertStatus(202);
    }

    private function webhookRequests(): array
    {
        return Http::recorded(fn (Request $request) => $request->url() === self::WEBHOOK)->all();
    }

    public function test_first_occurrence_is_forwarded_with_payload_and_auth_header(): void
    {
        $this->ingest($this->project);

        $this->assertCount(1, $this->webhookRequests());

        $issue = Issue::first();

        Http::assertSent(function (Request $request) use ($issue) {
            if ($request->url() !== self::WEBHOOK) {
                return false;
            }

            $this->assertSame('POST', $request->method());
            $this->assertSame(['Bearer s3cr3t-webhook'], $request->header('Authorization'));
            $this->assertStringContainsString('application/json', $request->header('Content-Type')[0]);

            $data = $request->data();
            $this->assertSame([
                'project', 'environment', 'exception', 'message', 'file', 'line', 'trace',
                'url', 'method', 'occurrences', 'first_seen', 'last_seen', 'error_url',
            ], array_keys($data));

            $this->assertSame('products-that-matter', $data['project']);
            $this->assertSame('production', $data['environment']);
            $this->assertSame('RuntimeException', $data['exception']);
            $this->assertSame('Login failed for password=[REDACTED]', $data['message']);
            $this->assertSame('/var/www/html/app/Services/Foo.php', $data['file']);
            $this->assertSame(42, $data['line']);
            $this->assertStringContainsString('#0 /var/www/html/app/Http/Controllers/FooController.php(17): App\\Services\\Foo->run()', $data['trace']);
            $this->assertSame('https://shop.test/checkout?step=2&api_token=[REDACTED]', $data['url']);
            $this->assertSame('POST', $data['method']);
            $this->assertSame(1, $data['occurrences']);
            $this->assertSame('2026-10-29T10:00:00+00:00', $data['first_seen']);
            $this->assertSame('2026-10-29T10:00:00+00:00', $data['last_seen']);
            $this->assertStringEndsWith('/issues/'.$issue->id, $data['error_url']);

            // Nothing secret from the event leaks through anywhere in the body.
            foreach (['hunter2', 'abc123', 'xyz', 'leaked', 'dbpass', 'laravel_session'] as $secret) {
                $this->assertStringNotContainsString($secret, $request->body());
            }

            return true;
        });
    }

    public function test_repeat_occurrences_are_not_forwarded(): void
    {
        $this->ingest($this->project);
        $this->ingest($this->project, ['exception' => ['message' => 'Different message, same fingerprint']]);
        $this->ingest($this->project);

        $this->assertSame(3, Issue::first()->occurrence_count);
        $this->assertCount(1, $this->webhookRequests());
    }

    public function test_resolved_issue_coming_back_is_forwarded_again(): void
    {
        $this->ingest($this->project);
        Issue::first()->update(['status' => IssueStatus::Resolved]);

        $this->ingest($this->project);
        $this->ingest($this->project);

        $requests = $this->webhookRequests();
        $this->assertCount(2, $requests);
        $this->assertSame(2, $requests[1][0]['occurrences']);
    }

    public function test_project_filter_limits_forwarding(): void
    {
        config()->set('services.error_webhook.projects', ' other-project , products-that-matter ');

        $excluded = Project::factory()->create(['slug' => 'not-listed']);

        $this->ingest($excluded);
        $this->assertCount(0, $this->webhookRequests());

        $this->ingest($this->project);
        $this->assertCount(1, $this->webhookRequests());
        Http::assertSent(fn (Request $request) => $request->url() !== self::WEBHOOK || $request['project'] === 'products-that-matter');
    }

    public function test_nothing_is_forwarded_without_a_url(): void
    {
        config()->set('services.error_webhook.url', '');
        Queue::fake();

        $this->ingest($this->project);

        Queue::assertNotPushed(ForwardErrorToWebhookJob::class);
        $this->assertCount(0, $this->webhookRequests());
    }

    public function test_forwarding_is_queued_on_the_alerts_queue(): void
    {
        Queue::fake();

        $this->ingest($this->project);

        Queue::assertPushedOn('alerts', ForwardErrorToWebhookJob::class);
        $this->assertCount(0, $this->webhookRequests());
    }

    public function test_webhook_failure_does_not_break_ingestion(): void
    {
        Http::fake([self::WEBHOOK => Http::response('boom', 500)]);

        $this->ingest($this->project);

        $this->assertSame(1, Issue::count());
    }

    public function test_job_retries_with_backoff(): void
    {
        $job = new ForwardErrorToWebhookJob([]);

        $this->assertSame(4, $job->tries);
        $this->assertCount(3, $job->backoff());
    }

    public function test_command_sends_sample_error(): void
    {
        $this->artisan('errors:test-webhook', ['project' => 'products-that-matter'])
            ->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->url() === self::WEBHOOK
            && $request->header('Authorization') === ['Bearer s3cr3t-webhook']
            && $request['project'] === 'products-that-matter'
            && $request['exception'] === 'RuntimeException'
            && $request['environment'] === 'testing');
    }

    public function test_command_environment_can_be_overridden(): void
    {
        $this->artisan('errors:test-webhook', ['project' => 'products-that-matter', '--environment' => 'production'])
            ->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request['environment'] === 'production');
    }

    public function test_command_fails_for_unknown_project(): void
    {
        $this->artisan('errors:test-webhook', ['project' => 'nope'])->assertFailed();

        Http::assertNothingSent();
    }
}
