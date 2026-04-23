@php
    $events = $getRecord()->events()->latest('received_at')->limit(20)->get();
@endphp

<ul class="divide-y divide-gray-200 text-sm dark:divide-gray-700">
    @forelse ($events as $evt)
        <li class="flex items-center justify-between gap-4 py-2">
            <div class="font-mono text-xs text-gray-500">#{{ $evt->id }}</div>
            <div class="flex-1">
                <span class="font-mono">{{ data_get($evt->payload, 'exception.class', data_get($evt->payload, 'log.level', '—')) }}</span>
                @if ($evt->environment)
                    <span class="ms-2 rounded bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-800">{{ $evt->environment }}</span>
                @endif
            </div>
            <div class="text-xs text-gray-500">{{ $evt->received_at?->diffForHumans() }}</div>
        </li>
    @empty
        <li class="py-2 text-sm text-gray-500">No events yet.</li>
    @endforelse
</ul>
