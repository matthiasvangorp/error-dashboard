@php
    $issue = $getRecord();
    $rows = $issue->events()
        ->selectRaw('DATE(received_at) as d, COUNT(*) as c')
        ->where('received_at', '>=', now()->subDays(30))
        ->groupBy('d')
        ->orderBy('d')
        ->get();
    $labels = $rows->pluck('d')->map(fn ($d) => (string) $d);
    $data = $rows->pluck('c');
    $chartId = 'occurrence-'.$issue->id;
@endphp

<div>
    <canvas id="{{ $chartId }}" height="80"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    (function () {
        const ctx = document.getElementById(@json($chartId));
        if (!ctx) return;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json($labels),
                datasets: [{
                    label: 'Events',
                    data: @json($data),
                    backgroundColor: 'rgba(239, 68, 68, 0.6)',
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, precision: 0 } },
            },
        });
    })();
</script>
