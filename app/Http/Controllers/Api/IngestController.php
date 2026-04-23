<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\IngestEventRequest;
use App\Jobs\DispatchAlertJob;
use App\Models\Project;
use App\Services\IssueUpsertService;
use Illuminate\Http\JsonResponse;

class IngestController
{
    public function __invoke(IngestEventRequest $request, IssueUpsertService $upsert): JsonResponse
    {
        /** @var Project $project */
        $project = $request->attributes->get('project');

        $result = $upsert->ingest($project, $request->validated());

        if ($result['alert_reason'] !== null) {
            DispatchAlertJob::dispatch($result['issue']->id, $result['event']->id, $result['alert_reason'])
                ->onQueue('alerts');
        }

        return response()->json([
            'issue_id' => $result['issue']->id,
            'event_id' => $result['event']->id,
        ], 202);
    }
}
