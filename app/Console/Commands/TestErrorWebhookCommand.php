<?php

namespace App\Console\Commands;

use App\Jobs\ForwardErrorToWebhookJob;
use App\Models\Issue;
use App\Models\Project;
use App\Services\ErrorWebhook;
use Illuminate\Console\Command;

class TestErrorWebhookCommand extends Command
{
    protected $signature = 'errors:test-webhook
        {project : Project slug}
        {--queue : Dispatch through the queue worker instead of sending inline}
        {--environment= : Environment to put in the payload (default: testing). Laravel\'s global --env is accepted too}
        {--issue= : Resend this real issue (ID from its dashboard URL) instead of a fake sample}';

    protected $description = 'Send a fake sample error (or resend a real issue) for a project to ERROR_WEBHOOK_URL.';

    public function handle(ErrorWebhook $webhook): int
    {
        $project = Project::query()->where('slug', $this->argument('project'))->first();

        if (! $project) {
            $this->error("No project with slug \"{$this->argument('project')}\".");

            return self::FAILURE;
        }

        if (! $webhook->enabled()) {
            $this->error('ERROR_WEBHOOK_URL is empty, so forwarding is disabled.');

            return self::FAILURE;
        }

        if (! $webhook->forwardsProject($project->slug)) {
            $this->warn("\"{$project->slug}\" is not in ERROR_WEBHOOK_PROJECTS, so real errors for it are not forwarded. Sending anyway.");
        }

        $payload = $this->option('issue') !== null
            ? $this->issuePayload($webhook, $project)
            : $this->samplePayload($webhook, $project);

        if ($payload === null) {
            return self::FAILURE;
        }

        if ($this->option('queue')) {
            ForwardErrorToWebhookJob::dispatch($payload)->onQueue('alerts');
            $this->info('Queued on "alerts". Check the queue worker logs for the result.');

            return self::SUCCESS;
        }

        try {
            $response = $webhook->send($payload);
        } catch (\Throwable $e) {
            $this->error('Webhook failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Webhook responded '.$response->status().'.');
        $this->line(mb_substr($response->body(), 0, 1000));

        return self::SUCCESS;
    }

    /**
     * The real issue as live forwarding would have sent it, built from its
     * latest event. Its own environment is used; --environment doesn't apply.
     *
     * @return array<string, mixed>|null
     */
    private function issuePayload(ErrorWebhook $webhook, Project $project): ?array
    {
        $issue = Issue::query()
            ->where('project_id', $project->id)
            ->with(['project', 'lastEvent'])
            ->find($this->option('issue'));

        if (! $issue || ! $issue->lastEvent) {
            $this->error("No issue #{$this->option('issue')} with events in \"{$project->slug}\".");

            return null;
        }

        $this->line("Resending issue #{$issue->id}: {$issue->title}");

        return $webhook->payloadFor($issue, $issue->lastEvent);
    }

    /**
     * @return array<string, mixed>
     */
    private function samplePayload(ErrorWebhook $webhook, Project $project): array
    {
        $now = now()->utc()->toIso8601String();

        // --env is Laravel's global option (it also picks the environment the
        // app boots in), so it can't be redefined here; honour it as a fallback.
        $environment = $this->option('environment') ?: $this->option('env') ?: 'testing';

        return $webhook->buildPayload([
            'project' => $project->slug,
            'environment' => $environment,
            'exception' => 'RuntimeException',
            'message' => 'Sample error from errors:test-webhook — safe to ignore.',
            'file' => '/var/www/html/app/Http/Controllers/ExampleController.php',
            'line' => 42,
            'trace' => implode("\n", [
                'RuntimeException: Sample error from errors:test-webhook — safe to ignore. at /var/www/html/app/Http/Controllers/ExampleController.php:42',
                '#0 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/Controller.php(54): App\Http\Controllers\ExampleController->show()',
                '#1 /var/www/html/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(43): Illuminate\Routing\Controller->callAction()',
                '#2 /var/www/html/public/index.php(17): Illuminate\Foundation\Application->handleRequest()',
            ]),
            'url' => rtrim((string) config('app.url'), '/').'/example/42',
            'method' => 'GET',
            'occurrences' => 1,
            'first_seen' => $now,
            'last_seen' => $now,
            'error_url' => rtrim((string) config('app.url'), '/').'/issues',
        ]);
    }
}
