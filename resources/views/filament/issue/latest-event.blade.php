@php
    $event = $getRecord()->lastEvent;
    $payload = $event?->payload ?? [];
    $exception = $payload['exception'] ?? null;
    $context = $payload['context'] ?? [];
    $preStyle = 'background:#0f172a;color:#e2e8f0;padding:0.75rem;border-radius:0.375rem;font-size:0.75rem;line-height:1.5;overflow-x:auto;white-space:pre-wrap;word-break:break-word;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;';
@endphp

@if ($event)
    <div style="display:flex;flex-direction:column;gap:1rem;font-size:0.875rem;">
        @if ($exception)
            <div>
                <div style="font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-weight:600;font-size:1rem;">
                    {{ $exception['class'] ?? 'Exception' }}
                </div>
                <div style="margin-top:0.25rem;">{{ $exception['message'] ?? '' }}</div>
                <div style="margin-top:0.25rem;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:0.75rem;color:#6b7280;">
                    in {{ $exception['file'] ?? '?' }}:{{ $exception['line'] ?? '?' }}
                </div>
            </div>
            @if (! empty($exception['trace']))
                <pre style="{{ $preStyle }}">{{ is_string($exception['trace']) ? $exception['trace'] : json_encode($exception['trace'], JSON_PRETTY_PRINT) }}</pre>
            @endif
        @endif

        @if (! empty($context))
            <div>
                <div style="margin-bottom:0.25rem;font-size:0.75rem;text-transform:uppercase;color:#6b7280;letter-spacing:0.05em;">Request</div>
                <dl style="display:grid;grid-template-columns:auto 1fr;gap:0.25rem 1rem;">
                    @foreach (['method', 'url', 'user_id', 'ip', 'release', 'environment'] as $field)
                        @if (! empty($context[$field]))
                            <dt style="color:#6b7280;">{{ $field }}</dt>
                            <dd style="font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;">{{ $context[$field] }}</dd>
                        @endif
                    @endforeach
                </dl>
            </div>
        @endif

        <details>
            <summary style="cursor:pointer;font-size:0.75rem;color:#6b7280;">View raw JSON</summary>
            <pre style="{{ $preStyle }} margin-top:0.5rem;">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
    </div>
@else
    <div style="font-size:0.875rem;color:#6b7280;">No events attached.</div>
@endif
