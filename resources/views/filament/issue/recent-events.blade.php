@php
    $events = $getRecord()->events()->latest('received_at')->limit(20)->get();
@endphp

<ul style="font-size:0.875rem;">
    @forelse ($events as $evt)
        <li style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0.5rem 0;border-top:1px solid #e5e7eb;">
            <div style="font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:0.75rem;color:#6b7280;">#{{ $evt->id }}</div>
            <div style="flex:1;">
                <span style="font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;">{{ data_get($evt->payload, 'exception.class', data_get($evt->payload, 'log.level', '—')) }}</span>
                @if ($evt->environment)
                    <span style="margin-left:0.5rem;padding:0.125rem 0.5rem;border-radius:0.25rem;background:#f3f4f6;font-size:0.75rem;">{{ $evt->environment }}</span>
                @endif
            </div>
            <div style="font-size:0.75rem;color:#6b7280;">{{ $evt->received_at?->diffForHumans() }}</div>
        </li>
    @empty
        <li style="padding:0.5rem 0;font-size:0.875rem;color:#6b7280;">No events yet.</li>
    @endforelse
</ul>
