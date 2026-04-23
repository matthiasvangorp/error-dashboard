<?php

namespace App\Services;

use App\Enums\IssueLevel;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Event;
use App\Models\Issue;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class IssueUpsertService
{
    public function __construct(private readonly FingerprintService $fingerprints)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{issue: Issue, event: Event, alert_reason: ?string}
     */
    public function ingest(Project $project, array $payload): array
    {
        [$fingerprint, $title, $type, $level] = $this->classify($payload);

        $context = (array) ($payload['context'] ?? []);
        $environment = $context['environment'] ?? null;
        $release = $context['release'] ?? null;

        $receivedAt = isset($payload['timestamp'])
            ? CarbonImmutable::parse($payload['timestamp'])
            : CarbonImmutable::now();

        return DB::transaction(function () use (
            $project, $payload, $fingerprint, $title, $type, $level, $environment, $release, $receivedAt
        ) {
            $issue = Issue::query()
                ->where('project_id', $project->id)
                ->where('fingerprint', $fingerprint)
                ->lockForUpdate()
                ->first();

            $wasNewlyCreated = false;
            $wasReopened = false;

            if (! $issue) {
                $issue = new Issue([
                    'project_id' => $project->id,
                    'fingerprint' => $fingerprint,
                    'title' => $title,
                    'type' => $type,
                    'level' => $level,
                    'status' => IssueStatus::Open,
                    'environment' => $environment,
                    'first_seen_at' => $receivedAt,
                    'last_seen_at' => $receivedAt,
                    'occurrence_count' => 0,
                ]);
                $issue->save();
                $wasNewlyCreated = true;
            } else {
                if ($issue->status === IssueStatus::Resolved) {
                    $issue->status = IssueStatus::Open;
                    $wasReopened = true;
                }
                // Refresh title/level in case they shift (e.g. more informative message later).
                $issue->title = $title;
                $issue->level = $level;
                $issue->environment = $environment ?? $issue->environment;
                $issue->last_seen_at = $receivedAt;
            }

            $event = Event::query()->create([
                'issue_id' => $issue->id,
                'project_id' => $project->id,
                'payload' => $payload,
                'environment' => $environment,
                'release' => $release,
                'received_at' => $receivedAt,
            ]);

            $issue->occurrence_count += 1;
            $issue->last_event_id = $event->id;
            $issue->save();

            $alertReason = match (true) {
                $wasNewlyCreated => 'new',
                $wasReopened => 'reopened',
                default => null,
            };

            return [
                'issue' => $issue,
                'event' => $event,
                'alert_reason' => $alertReason,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string, 2: IssueType, 3: IssueLevel}
     */
    private function classify(array $payload): array
    {
        $type = IssueType::from($payload['type']);

        if ($type === IssueType::Exception) {
            $exception = (array) ($payload['exception'] ?? []);
            $class = (string) ($exception['class'] ?? 'UnknownException');
            $message = (string) ($exception['message'] ?? '');
            $file = $exception['file'] ?? null;
            $line = isset($exception['line']) ? (int) $exception['line'] : null;

            return [
                $this->fingerprints->forException($class, $file, $line),
                $this->fingerprints->titleForException($class, $message),
                $type,
                IssueLevel::Error,
            ];
        }

        $log = (array) ($payload['log'] ?? []);
        $channel = (string) ($log['channel'] ?? 'default');
        $levelValue = strtolower((string) ($log['level'] ?? 'error'));
        $message = (string) ($log['message'] ?? '');

        $level = IssueLevel::tryFrom($levelValue) ?? IssueLevel::Error;

        return [
            $this->fingerprints->forLog($channel, $level->value, $message),
            $this->fingerprints->titleForLog($channel, $message),
            $type,
            $level,
        ];
    }
}
