<x-mail::message>
# {{ $reason === 'reopened' ? 'Issue reopened' : 'New issue' }} — {{ $issue->project->name }}

**{{ $issue->title }}**

- **Level:** {{ $issue->level->label() }}
- **Environment:** {{ $event->environment ?? '—' }}
- **First seen:** {{ $issue->first_seen_at->toDateTimeString() }}
- **Occurrences:** {{ $issue->occurrence_count }}

<x-mail::button :url="$url">
View issue
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
