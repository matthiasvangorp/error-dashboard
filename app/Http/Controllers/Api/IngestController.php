<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\IngestEventRequest;
use App\Jobs\DispatchAlertJob;
use App\Jobs\ForwardErrorToWebhookJob;
use App\Models\Event;
use App\Models\Issue;
use App\Models\Project;
use App\Services\ErrorWebhook;
use App\Services\IssueUpsertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class IngestController
{
    public function __invoke(IngestEventRequest $request, IssueUpsertService $upsert, ErrorWebhook $webhook): JsonResponse
    {
        /** @var Project $project */
        $project = $request->attributes->get('project');

        $result = $upsert->ingest($project, $request->validated());

        if ($result['alert_reason'] !== null) {
            DispatchAlertJob::dispatch($result['issue']->id, $result['event']->id, $result['alert_reason'])
                ->onQueue('alerts');
        }

        if ($result['forward_reason'] !== null) {
            $this->forwardToWebhook($webhook, $result['issue'], $result['event']);
        }

        return response()->json([
            'issue_id' => $result['issue']->id,
            'event_id' => $result['event']->id,
        ], 202);
    }

    /**
     * Never let the webhook break ingestion: building or queueing the payload
     * is best-effort, and delivery failures are handled inside the job.
     */
    private function forwardToWebhook(ErrorWebhook $webhook, Issue $issue, Event $event): void
    {
        try {
            if (! $webhook->shouldForward($issue)) {
                return;
            }

            ForwardErrorToWebhookJob::dispatch($webhook->payloadFor($issue, $event))->onQueue('alerts');
        } catch (\Throwable $e) {
            Log::warning('Could not queue error webhook', [
                'issue_id' => $issue->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
