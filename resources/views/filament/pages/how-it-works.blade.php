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
            <strong>The client package</strong> — <code style="{{ $kbdStyle }}">matthiasvangorp/error-reporter</code> — quietly watches your app for unhandled exceptions (and, if you turn it on, <code style="{{ $kbdStyle }}">Log::error()</code> calls). When one fires, it bundles up everything worth knowing — the error itself, the file and line it happened on, the stack trace, and a cleaned-up snapshot of the incoming request — and hands that package off to a background job. The job stamps it with a cryptographic fingerprint (more on that below) and posts it to this dashboard over HTTPS. The whole thing is designed to fail silently: if the dashboard is unreachable or slow, your app keeps serving traffic like nothing happened.
        </p>

        <p style="{{ $paraStyle }}">
            <strong>This dashboard</strong> listens on a single URL for those payloads. It first checks that the stamp matches what it expects from the sending app (so nobody can spoof events). Then it saves the raw payload as an <em>event</em>, and either creates a new <em>issue</em> for it (the first time this particular error is seen) or attaches it to an existing issue if something just like it has been reported before.
        </p>

        <h2 style="{{ $headingStyle }}">Why grouping matters</h2>

        <p style="{{ $paraStyle }}">
            A single bug usually fires thousands of times before anyone notices — every page refresh, every retry, every unlucky user. If every occurrence became its own issue, the dashboard would be useless noise. So each incoming event gets reduced to a short identifier — a "fingerprint" — built from the stable parts of the error (the exception class, the file and the line, or in the case of a log message, the log channel and a templated version of the message). Anything that varies from occurrence to occurrence is stripped out before fingerprinting. So <em>"User 1234 not found"</em> and <em>"User 5678 not found"</em> end up with the same fingerprint, and collapse into a single issue.
        </p>

        <p style="{{ $paraStyle }}">
            The second time a given fingerprint arrives, the dashboard just bumps a counter on the existing issue and updates when it was last seen. You see how often something's happening without drowning in duplicates.
        </p>

        <h2 style="{{ $headingStyle }}">Signal-to-noise rule for alerts</h2>

        <p style="{{ $paraStyle }}">
            Alerts (Telegram or email, configured per project) fire in exactly two cases: when an issue is created for the first time, or when an already-<em>resolved</em> issue is reopened by a new event. They never fire on repeat occurrences of an open issue — the counter ticks up quietly. This is the thing that keeps the notifications useful instead of turning into background radiation.
        </p>

        <h2 style="{{ $headingStyle }}">Making sure it's really your app</h2>

        <p style="{{ $paraStyle }}">
            Each project here has two pieces of ID: a <code style="{{ $kbdStyle }}">token</code> (it's part of the URL the client posts to — not secret, just an address) and a <code style="{{ $kbdStyle }}">secret</code> (a long random string that only the client and this dashboard know). Every time the client sends an event, it mixes the secret and the payload together to produce a tamper-evident stamp. The dashboard runs the same calculation with its copy of the secret — if the stamps match, the event is genuine; if they don't, the request is refused. Anyone who gets hold of the stream can't forge events without the secret, and can't alter a captured payload without the stamp no longer matching. The technical term for this is <strong>HMAC</strong> — shorthand for "a signature that proves both authenticity and integrity."
        </p>

        <p style="{{ $paraStyle }}">
            Because each project has its own token/secret pair, a leaked secret only affects that one project. Rotate it from the Projects page and the old one is dead immediately.
        </p>

        <h2 style="{{ $headingStyle }}">Not shipping passwords by accident</h2>

        <p style="{{ $paraStyle }}">
            Errors that happen during a real HTTP request can pick up form fields, headers, and session data on their way out — which is exactly what you want for debugging and exactly what you don't want if a user just submitted their password on the form that crashed. Before the client hands a payload to the background job, it walks through everything and redacts values whose key names look sensitive: <code style="{{ $kbdStyle }}">password</code>, <code style="{{ $kbdStyle }}">token</code>, <code style="{{ $kbdStyle }}">api_token</code>, <code style="{{ $kbdStyle }}">cookie</code>, <code style="{{ $kbdStyle }}">credit_card</code>, <code style="{{ $kbdStyle }}">cvv</code>, and a few more. The <code style="{{ $kbdStyle }}">Authorization</code> header is always blanked out regardless of configuration. You can add your own app-specific keys to the list (social security numbers, phone numbers, whatever) in each client app's config file.
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
