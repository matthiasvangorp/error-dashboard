@php
    $event = $getRecord()->lastEvent;
    $payload = $event?->payload ?? [];
    $exception = $payload['exception'] ?? null;
    $context = $payload['context'] ?? [];
@endphp

@if ($event)
    <div class="space-y-4 text-sm">
        @if ($exception)
            <div>
                <div class="font-mono text-base font-semibold text-gray-900 dark:text-gray-100">
                    {{ $exception['class'] ?? 'Exception' }}
                </div>
                <div class="mt-1 text-gray-700 dark:text-gray-300">
                    {{ $exception['message'] ?? '' }}
                </div>
                <div class="mt-1 font-mono text-xs text-gray-500">
                    in {{ $exception['file'] ?? '?' }}:{{ $exception['line'] ?? '?' }}
                </div>
            </div>
            @if (! empty($exception['trace']))
                <pre class="overflow-x-auto whitespace-pre-wrap rounded bg-gray-900 p-3 text-xs text-gray-100">{{ is_string($exception['trace']) ? $exception['trace'] : json_encode($exception['trace'], JSON_PRETTY_PRINT) }}</pre>
            @endif
        @endif

        @if (! empty($context))
            <div>
                <div class="mb-1 text-xs uppercase text-gray-500">Request</div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1">
                    @foreach (['method', 'url', 'user_id', 'ip', 'release', 'environment'] as $field)
                        @if (! empty($context[$field]))
                            <dt class="text-gray-500">{{ $field }}</dt>
                            <dd class="font-mono">{{ $context[$field] }}</dd>
                        @endif
                    @endforeach
                </dl>
            </div>
        @endif

        <details>
            <summary class="cursor-pointer text-xs text-gray-500 hover:text-gray-700">View raw JSON</summary>
            <pre class="mt-2 overflow-x-auto whitespace-pre-wrap rounded bg-gray-900 p-3 text-xs text-gray-100">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
    </div>
@else
    <div class="text-sm text-gray-500">No events attached.</div>
@endif
