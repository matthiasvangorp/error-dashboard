<?php

namespace App\Services;

use App\Filament\Resources\IssueResource;
use App\Models\Event;
use App\Models\Issue;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Forwards issues to an external webhook (ERROR_WEBHOOK_URL). Only a fixed set
 * of fields is sent — never request headers, cookies or the raw event context —
 * and free-text fields are scrubbed of anything that looks like a credential.
 */
class ErrorWebhook
{
    public const TIMEOUT_SECONDS = 10;

    private const REDACTED = '[REDACTED]';

    // Names whose values must never leave the dashboard (query params, key=value pairs, env vars).
    private const SENSITIVE_KEY = '[\w.-]*(?:pass(?:word|wd)?|pwd|secret|token|apikey|[_-]key\b|credential|auth(?:orization)?|cookie|session|signature)[\w.-]*';

    public function __construct(
        private readonly ?string $url,
        private readonly ?string $auth,
        private readonly ?string $projects,
    ) {
    }

    public function enabled(): bool
    {
        return trim((string) $this->url) !== '';
    }

    public function forwardsProject(string $slug): bool
    {
        $allowed = $this->allowedProjects();

        return $allowed === [] || in_array($slug, $allowed, true);
    }

    /**
     * @return array<int, string>
     */
    public function allowedProjects(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->projects))));
    }

    public function shouldForward(Issue $issue): bool
    {
        return $this->enabled() && $this->forwardsProject($issue->project->slug);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(array $payload): Response
    {
        if (! $this->enabled()) {
            throw new RuntimeException('ERROR_WEBHOOK_URL is not configured.');
        }

        $request = Http::asJson()
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS);

        if (trim((string) $this->auth) !== '') {
            $request = $request->withHeaders(['Authorization' => $this->auth]);
        }

        $response = $request->post($this->url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Error webhook responded '.$response->status().': '.mb_substr($response->body(), 0, 500));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadFor(Issue $issue, Event $event): array
    {
        $data = (array) ($event->payload ?? []);
        $exception = isset($data['exception']) ? (array) $data['exception'] : null;
        $log = isset($data['log']) ? (array) $data['log'] : null;
        $context = (array) ($data['context'] ?? []);

        return $this->buildPayload([
            'project' => $issue->project->slug,
            'environment' => $event->environment ?? $issue->environment,
            'exception' => $exception['class'] ?? null,
            'message' => $exception !== null ? ($exception['message'] ?? '') : ($log['message'] ?? $issue->title),
            'file' => $exception['file'] ?? null,
            'line' => isset($exception['line']) ? (int) $exception['line'] : null,
            'trace' => $exception !== null ? $this->formatException($exception) : null,
            'url' => $context['url'] ?? null,
            'method' => $context['method'] ?? null,
            'occurrences' => $issue->occurrence_count,
            'first_seen' => $issue->first_seen_at?->toIso8601String(),
            'last_seen' => $issue->last_seen_at?->toIso8601String(),
            'error_url' => IssueResource::getUrl('view', ['record' => $issue->id], panel: 'admin'),
        ]);
    }

    /**
     * Normalize and scrub a payload. Public so the test command can push a
     * hand-built sample through exactly the same sanitizing.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function buildPayload(array $fields): array
    {
        return [
            'project' => (string) $fields['project'],
            'environment' => $fields['environment'] ?? null,
            'exception' => $fields['exception'] ?? null,
            'message' => $this->scrubText((string) ($fields['message'] ?? '')),
            'file' => $fields['file'] ?? null,
            'line' => isset($fields['line']) ? (int) $fields['line'] : null,
            'trace' => isset($fields['trace']) ? $this->scrubText((string) $fields['trace']) : null,
            'url' => isset($fields['url']) ? $this->scrubText((string) $fields['url']) : null,
            'method' => isset($fields['method']) ? strtoupper((string) $fields['method']) : null,
            'occurrences' => (int) ($fields['occurrences'] ?? 1),
            'first_seen' => $fields['first_seen'] ?? null,
            'last_seen' => $fields['last_seen'] ?? null,
            'error_url' => $fields['error_url'] ?? null,
        ];
    }

    /**
     * Renders the exception chain as plain text. Frames only carry
     * file/line/class/function — call arguments are deliberately dropped.
     *
     * @param  array<string, mixed>  $exception
     */
    private function formatException(array $exception, int $depth = 0): string
    {
        $lines = [
            ($exception['class'] ?? 'Exception').': '.($exception['message'] ?? '')
            .' at '.($exception['file'] ?? '?').':'.($exception['line'] ?? '?'),
        ];

        $trace = $exception['trace'] ?? [];
        if (is_string($trace)) {
            $lines[] = $trace;
        } elseif (is_array($trace)) {
            foreach (array_values($trace) as $i => $frame) {
                if (! is_array($frame)) {
                    continue;
                }
                $call = trim(($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? ''));
                $lines[] = '#'.$i.' '.($frame['file'] ?? '[internal]').'('.($frame['line'] ?? '?').'): '
                    .($call !== '' ? $call.'()' : '');
            }
        }

        if ($depth < 10 && ! empty($exception['previous']) && is_array($exception['previous'])) {
            $lines[] = '';
            $lines[] = 'Caused by: '.$this->formatException($exception['previous'], $depth + 1);
        }

        return implode("\n", $lines);
    }

    private function scrubText(string $text): string
    {
        $key = self::SENSITIVE_KEY;

        $patterns = [
            // Credentials embedded in URLs/DSNs: scheme://user:pass@host
            '#(\w+://)[^/\s:@]+:[^/\s@]+@#' => '$1'.self::REDACTED.'@',
            // Authorization schemes in free text.
            '/\b(Bearer|Basic|Token)\s+[A-Za-z0-9\-._~+\/]+=*/i' => '$1 '.self::REDACTED,
            // Sensitive query string parameters.
            '/([?&]'.$key.'=)[^&#\s"\']*/i' => '$1'.self::REDACTED,
            // key=value, key: value, 'key' => 'value', "key":"value", DB_PASSWORD=value
            '/(["\']?'.$key.'["\']?\s*(?:=>|=|:)\s*["\']?)(?!(?:\w+ )?\[REDACTED\])[^\s"\'&,;)\]}]+/i' => '$1'.self::REDACTED,
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $text);
    }
}
