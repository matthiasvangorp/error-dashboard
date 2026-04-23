@php
    $paraStyle = 'margin-bottom:1rem;line-height:1.7;';
    $headingStyle = 'font-size:1.125rem;font-weight:600;margin-top:1.75rem;margin-bottom:0.5rem;';
    $kbdStyle = 'background:rgba(148,163,184,0.2);border:1px solid rgba(148,163,184,0.3);border-radius:0.25rem;padding:0.05rem 0.35rem;font-size:0.8125rem;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;color:inherit;';
    $linkStyle = 'color:#2563eb;text-decoration:underline;';
    $calloutStyle = 'border-left:3px solid #3b82f6;background:rgba(59,130,246,0.12);padding:0.75rem 1rem;margin:1rem 0;font-size:0.9375rem;border-radius:0 0.25rem 0.25rem 0;color:inherit;';
@endphp

<x-filament-panels::page>
    <div style="max-width:52rem;font-size:0.9375rem;line-height:1.65;">

        <p style="{{ $paraStyle }}">
            This is a self-hosted, stripped-down Sentry — narrower in scope, simpler to run, and tuned for a small portfolio of Laravel apps. Two pieces make it work: <strong>the client package</strong> that lives inside each of your Laravel apps, and <strong>this dashboard</strong> that receives and groups what they send.
        </p>

        <h2 style="{{ $headingStyle }}">The two sides</h2>

        <p style="{{ $paraStyle }}">
            <strong>The client package</strong> — <code style="{{ $kbdStyle }}">matthiasvangorp/error-reporter</code> — hooks into your app's exception handler. When an unhandled exception fires (or, optionally, when you call <code style="{{ $kbdStyle }}">Log::error()</code>), the package builds a structured payload: the exception class, message, file, line, stack trace, plus a scrubbed snapshot of the request (URL, method, user id, IP, headers, request body). It signs that payload with an HMAC and dispatches a queued job that POSTs it to this dashboard over HTTPS. The whole thing is designed to fail silently — if the dashboard is unreachable, your app keeps serving traffic without noticing.
        </p>

        <p style="{{ $paraStyle }}">
            <strong>This dashboard</strong> exposes one ingest endpoint — <code style="{{ $kbdStyle }}">POST {{ $endpoint }}/api/ingest/&lbrace;project_token&rbrace;</code> — that verifies the HMAC signature against the project's secret, rate-limits the request, parses the payload, and writes two rows: an <em>event</em> (the raw payload as-received) and, unless one already exists with the same fingerprint, an <em>issue</em> (the deduped grouping the event belongs to).
        </p>

        <h2 style="{{ $headingStyle }}">Why fingerprinting</h2>

        <p style="{{ $paraStyle }}">
            A single bug usually fires thousands of times before anyone notices. Storing each occurrence as a separate issue would make the dashboard useless. So every incoming event is hashed into a short "fingerprint" — for exceptions, <code style="{{ $kbdStyle }}">sha256(class + normalized_file + line)</code>; for log events, <code style="{{ $kbdStyle }}">sha256(channel + level + templatized_message)</code>. The log fingerprint replaces numbers, UUIDs, and quoted strings with placeholders first, so <em>"User 1234 not found"</em> and <em>"User 5678 not found"</em> collapse into the same issue.
        </p>

        <p style="{{ $paraStyle }}">
            The second time a fingerprint arrives, the dashboard just increments a counter and updates <code style="{{ $kbdStyle }}">last_seen_at</code> on the existing issue. You see how often something happens without drowning in duplicates.
        </p>

        <h2 style="{{ $headingStyle }}">Signal-to-noise rule for alerts</h2>

        <p style="{{ $paraStyle }}">
            Alerts (Telegram or email, configured per project) fire in exactly two cases: when an issue is created for the first time, or when an already-<em>resolved</em> issue is reopened by a new event. They never fire on repeat occurrences of an open issue — the counter ticks up quietly. This is the thing that keeps the notifications useful instead of turning into background radiation.
        </p>

        <h2 style="{{ $headingStyle }}">Security + privacy</h2>

        <p style="{{ $paraStyle }}">
            Each project has a <code style="{{ $kbdStyle }}">token</code> (public path segment) and a <code style="{{ $kbdStyle }}">secret</code> (HMAC signing key). Every request must carry an <code style="{{ $kbdStyle }}">X-Signature</code> header that the dashboard verifies with <code style="{{ $kbdStyle }}">hash_equals</code> — an invalid signature gets a 401 and nothing is written. The token/secret pair is scoped to one project, so a leaked secret only affects that project's stream.
        </p>

        <p style="{{ $paraStyle }}">
            Before a payload leaves the client, the package scrubs a list of known-sensitive keys from request data, headers, session, and any custom context you attach: <code style="{{ $kbdStyle }}">password</code>, <code style="{{ $kbdStyle }}">token</code>, <code style="{{ $kbdStyle }}">api_token</code>, <code style="{{ $kbdStyle }}">cookie</code>, <code style="{{ $kbdStyle }}">credit_card</code>, <code style="{{ $kbdStyle }}">cvv</code>, etc. The <code style="{{ $kbdStyle }}">Authorization</code> header is always redacted regardless of the configured list. The scrub list is extensible per-project.
        </p>

        <h2 style="{{ $headingStyle }}">Data lifecycle</h2>

        <p style="{{ $paraStyle }}">
            Every project has a retention window (default 30 days). A scheduled command, <code style="{{ $kbdStyle }}">errors:prune</code>, runs daily and deletes events older than that window. Issues themselves stay — you keep the aggregate history ("this bug fired 4,837 times across six months") without keeping every individual payload. Resolved issues with no events in 90 days get cleaned up too, so the dashboard doesn't grow without bound.
        </p>

        <h2 style="{{ $headingStyle }}">What's deliberately missing</h2>

        <p style="{{ $paraStyle }}">
            No performance tracing, no source map upload, no release artifact pipeline, no user-feedback widget, no multi-tenant user accounts. Adding those is the path to becoming Sentry, and that's not the goal. The goal is: <em>something broke, I get a notification, I click through to a page that tells me what it was, what it was doing, and how often it's still happening.</em>
        </p>

        <div style="{{ $calloutStyle }}">
            To add a new Laravel project to this dashboard, see the <a href="{{ \App\Filament\Pages\SetupGuide::getUrl() }}" style="{{ $linkStyle }}">Setup Guide</a>.
        </div>

    </div>
</x-filament-panels::page>
