<?php

namespace App\Jobs;

use App\Services\ErrorWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ForwardErrorToWebhookJob implements ShouldQueue
{
    use Queueable;

    // First attempt + 3 retries.
    public int $tries = 4;

    // Must outlive the 10s HTTP timeout.
    public int $timeout = 30;

    /**
     * The payload is built (and scrubbed) at dispatch time, so the job carries
     * a snapshot of the issue as it was when it was first seen / reopened.
     *
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload)
    {
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(ErrorWebhook $webhook): void
    {
        // Config may have changed between dispatch and run.
        if (! $webhook->enabled()) {
            return;
        }

        $webhook->send($this->payload);
    }

    public function failed(?Throwable $e): void
    {
        Log::warning('Error webhook forwarding failed', [
            'project' => $this->payload['project'] ?? null,
            'error_url' => $this->payload['error_url'] ?? null,
            'error' => $e?->getMessage(),
        ]);
    }
}
