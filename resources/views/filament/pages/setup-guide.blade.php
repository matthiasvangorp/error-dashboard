@php
    $codeBlockStyle = 'background:#0f172a;color:#e2e8f0;padding:0.75rem 1rem;border-radius:0.375rem;font-size:0.8125rem;line-height:1.55;overflow-x:auto;white-space:pre;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;margin:0.5rem 0 1rem;';
    $sectionStyle = 'margin-bottom:2rem;';
    $headingStyle = 'font-size:1.125rem;font-weight:600;margin-top:1.5rem;margin-bottom:0.5rem;';
    $subHeadingStyle = 'font-size:0.875rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-top:1rem;margin-bottom:0.25rem;';
    $paraStyle = 'margin-bottom:0.75rem;line-height:1.6;';
    $liStyle = 'margin-bottom:0.35rem;';
    $kbdStyle = 'background:#f3f4f6;border:1px solid #e5e7eb;border-radius:0.25rem;padding:0.05rem 0.35rem;font-size:0.8125rem;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;';
    $calloutStyle = 'border-left:3px solid #f59e0b;background:#fffbeb;padding:0.75rem 1rem;margin:0.75rem 0;font-size:0.875rem;border-radius:0 0.25rem 0.25rem 0;';
    $calloutInfoStyle = 'border-left:3px solid #3b82f6;background:#eff6ff;padding:0.75rem 1rem;margin:0.75rem 0;font-size:0.875rem;border-radius:0 0.25rem 0.25rem 0;';
    $linkStyle = 'color:#2563eb;text-decoration:underline;';
@endphp

<x-filament-panels::page>
    <div style="max-width:56rem;font-size:0.9375rem;line-height:1.6;">

        <p style="{{ $paraStyle }}">
            How to wire a Laravel app into this dashboard so unhandled exceptions (and optionally log entries) ship here over HMAC-signed HTTPS.
        </p>

        <p style="{{ $paraStyle }}">
            <strong>Collector endpoint:</strong> <code style="{{ $kbdStyle }}">{{ $endpoint }}</code><br>
            <strong>Client package:</strong> <code style="{{ $kbdStyle }}">{{ $packageName }}</code> &mdash; private, pulled from GitHub over SSH
        </p>

        {{-- Step 1 --}}
        <div style="{{ $sectionStyle }}">
            <h2 style="{{ $headingStyle }}">1. Create a project here</h2>
            <p style="{{ $paraStyle }}">
                <a href="{{ \App\Filament\Resources\ProjectResource::getUrl('create') }}" style="{{ $linkStyle }}">Create a new project</a>
                (or open an <a href="{{ \App\Filament\Resources\ProjectResource::getUrl('index') }}" style="{{ $linkStyle }}">existing one</a>) and copy the <code style="{{ $kbdStyle }}">token</code> + <code style="{{ $kbdStyle }}">secret</code>. You'll paste both into the client's <code style="{{ $kbdStyle }}">.env</code> below.
            </p>
            <div style="{{ $calloutInfoStyle }}">
                The <strong>slug</strong> is for the URL only. The <strong>token</strong> is the path segment that identifies the project at ingest time. The <strong>secret</strong> is the HMAC key — both must be kept in sync with the client.
            </div>
        </div>

        {{-- Step 2 --}}
        <div style="{{ $sectionStyle }}">
            <h2 style="{{ $headingStyle }}">2. Install the client package</h2>
            <p style="{{ $paraStyle }}">In the target Laravel app's <code style="{{ $kbdStyle }}">composer.json</code>, add the repository + require:</p>
            <pre style="{{ $codeBlockStyle }}">"require": {
    "matthiasvangorp/error-reporter": "dev-main"
},
"repositories": [
    { "type": "vcs", "url": "{{ $packageUrl }}" }
]</pre>
            <p style="{{ $paraStyle }}">Then, inside the app container (or on any machine with SSH access to the private GitHub repo):</p>
            <pre style="{{ $codeBlockStyle }}">composer update matthiasvangorp/error-reporter --prefer-dist</pre>
            <p style="{{ $paraStyle }}">The service provider is auto-discovered via <code style="{{ $kbdStyle }}">extra.laravel.providers</code>. The package supports Laravel 10, 11, and 12 on PHP 8.2+.</p>
        </div>

        {{-- Step 3 --}}
        <div style="{{ $sectionStyle }}">
            <h2 style="{{ $headingStyle }}">3. Add env vars</h2>
            <p style="{{ $paraStyle }}">Append to the client app's <code style="{{ $kbdStyle }}">.env</code>:</p>
            <pre style="{{ $codeBlockStyle }}"># Error Reporter → {{ $endpoint }}
ERROR_REPORTER_ENABLED=true
ERROR_REPORTER_ENDPOINT={{ $endpoint }}
ERROR_REPORTER_TOKEN=<paste from project page>
ERROR_REPORTER_SECRET=<paste from project page>
ERROR_REPORTER_RELEASE=
ERROR_REPORTER_QUEUE=default
ERROR_REPORTER_LOG_ENABLED=false</pre>
            <p style="{{ $paraStyle }}">Also add the same block (with blank <code style="{{ $kbdStyle }}">TOKEN</code> / <code style="{{ $kbdStyle }}">SECRET</code>) to <code style="{{ $kbdStyle }}">.env.example</code> so other developers get a template.</p>
            <p style="{{ $paraStyle }}">
                Optional knobs:
            </p>
            <ul style="padding-left:1.25rem;">
                <li style="{{ $liStyle }}"><code style="{{ $kbdStyle }}">ERROR_REPORTER_RELEASE</code> — commit SHA for release tagging. Set via <code style="{{ $kbdStyle }}">git rev-parse --short HEAD</code> in your deploy script.</li>
                <li style="{{ $liStyle }}"><code style="{{ $kbdStyle }}">ERROR_REPORTER_QUEUE_CONNECTION</code> — route the ship job to a specific connection (default is whatever <code style="{{ $kbdStyle }}">QUEUE_CONNECTION</code> is).</li>
                <li style="{{ $liStyle }}"><code style="{{ $kbdStyle }}">ERROR_REPORTER_LOG_ENABLED=true</code> + adding an <code style="{{ $kbdStyle }}">error-reporter</code> channel to <code style="{{ $kbdStyle }}">config/logging.php</code> — ship <code style="{{ $kbdStyle }}">Log::error()</code> / <code style="{{ $kbdStyle }}">Log::warning()</code> too.</li>
            </ul>
        </div>

        {{-- Step 4 --}}
        <div style="{{ $sectionStyle }}">
            <h2 style="{{ $headingStyle }}">4. Exception wiring</h2>
            <p style="{{ $paraStyle }}"><strong>Laravel 11/12:</strong> zero config &mdash; the service provider auto-hooks <code style="{{ $kbdStyle }}">ExceptionHandler::reportable()</code>.</p>
            <p style="{{ $paraStyle }}"><strong>Laravel 10:</strong> same auto-hook works if you use the default framework <code style="{{ $kbdStyle }}">Handler</code>. If you've overridden <code style="{{ $kbdStyle }}">report()</code> in <code style="{{ $kbdStyle }}">App\Exceptions\Handler</code>, add the trait:</p>
            <pre style="{{ $codeBlockStyle }}">use MatthiasVanGorp\ErrorReporter\Concerns\ReportsToErrorReporter;

class Handler extends ExceptionHandler
{
    use ReportsToErrorReporter;
    // …
}</pre>
        </div>

        {{-- Step 5 --}}
        <div style="{{ $sectionStyle }}">
            <h2 style="{{ $headingStyle }}">5. Commit + deploy</h2>
            <p style="{{ $paraStyle }}">Commit <code style="{{ $kbdStyle }}">composer.json</code>, <code style="{{ $kbdStyle }}">composer.lock</code>, and <code style="{{ $kbdStyle }}">.env.example</code>. Push to your remote.</p>

            <h3 style="{{ $subHeadingStyle }}">On the production server</h3>
            <p style="{{ $paraStyle }}">The server needs SSH access to the private <code style="{{ $kbdStyle }}">error-reporter</code> repo so <code style="{{ $kbdStyle }}">composer install</code> can fetch it.</p>
            <p style="{{ $paraStyle }}"><strong>One-time setup per server:</strong></p>
            <pre style="{{ $codeBlockStyle }}"># Generate a read-only deploy key for this server
ssh-keygen -t ed25519 -f ~/.ssh/error-reporter_server_deploy -N "" \
  -C "error-reporter server deploy (read-only)"
cat ~/.ssh/error-reporter_server_deploy.pub
# Register the pub key at:
#   github.com/matthiasvangorp/error-reporter/settings/keys/new
#   → leave "Allow write access" UNCHECKED

# Pin an SSH alias to this key (append to ~/.ssh/config):
cat &gt;&gt; ~/.ssh/config &lt;&lt;EOF

Host github-error-reporter
  HostName github.com
  User git
  IdentityFile ~/.ssh/error-reporter_server_deploy
  IdentitiesOnly yes
EOF

# Rewrite the public URL so composer uses that alias (and therefore the right key)
git config --global url."git@github-error-reporter:matthiasvangorp/error-reporter.git".insteadOf \
    "git@github.com:matthiasvangorp/error-reporter.git"</pre>

            <p style="{{ $paraStyle }}"><strong>Then deploy</strong> the client app normally:</p>
            <pre style="{{ $codeBlockStyle }}">cd /var/www/html/your-app
git pull
composer install --no-interaction --optimize-autoloader
# append ERROR_REPORTER_* to the server's .env
php artisan config:cache</pre>

            <div style="{{ $calloutStyle }}">
                <strong>Per-server, not per-app:</strong> one SSH key + one <code style="{{ $kbdStyle }}">insteadOf</code> rewrite covers every Laravel app on the same server. Skip this block on servers where it's already done.
            </div>
        </div>

        {{-- Step 6 --}}
        <div style="{{ $sectionStyle }}">
            <h2 style="{{ $headingStyle }}">6. Smoke test</h2>
            <pre style="{{ $codeBlockStyle }}">php artisan tinker
>>> app(\MatthiasVanGorp\ErrorReporter\ErrorReporter::class)
       ->captureException(new \RuntimeException('hello from production'));</pre>
            <p style="{{ $paraStyle }}">You should see a new issue appear under <a href="{{ \App\Filament\Resources\IssueResource::getUrl('index') }}" style="{{ $linkStyle }}">Issues</a> within seconds (once the queue worker for the client app picks it up).</p>
            <div style="{{ $calloutInfoStyle }}">
                If it doesn't show up: check the client app's queue worker is running, and tail <code style="{{ $kbdStyle }}">storage/logs/laravel.log</code> for any <code style="{{ $kbdStyle }}">error-reporter</code> warnings. The package fails silently by design &mdash; it never blocks the host app &mdash; so look in the log for soft failures.
            </div>
        </div>

        {{-- Reference --}}
        <div style="{{ $sectionStyle }}">
            <h2 style="{{ $headingStyle }}">Reference</h2>
            <ul style="padding-left:1.25rem;">
                <li style="{{ $liStyle }}"><strong>Fingerprinting:</strong> exceptions → <code style="{{ $kbdStyle }}">sha256(class | normalized_file | line)</code>; logs → <code style="{{ $kbdStyle }}">sha256(channel | level | templatized_message)</code>. Numbers, UUIDs, and quoted strings in log messages are replaced with placeholders so <em>"User 1234 not found"</em> and <em>"User 5678 not found"</em> collapse into one issue.</li>
                <li style="{{ $liStyle }}"><strong>Alerts:</strong> fire on new issue + on reopen (resolved → open). Never on subsequent occurrences of an already-open issue. Configure per-project on the project edit page.</li>
                <li style="{{ $liStyle }}"><strong>PII scrubbing:</strong> <code style="{{ $kbdStyle }}">password</code>, <code style="{{ $kbdStyle }}">token</code>, <code style="{{ $kbdStyle }}">api_token</code>, <code style="{{ $kbdStyle }}">authorization</code>, <code style="{{ $kbdStyle }}">cookie</code>, <code style="{{ $kbdStyle }}">credit_card</code>, etc. are redacted before shipping. <code style="{{ $kbdStyle }}">Authorization</code> is always redacted regardless of config.</li>
                <li style="{{ $liStyle }}"><strong>Rate limit:</strong> configurable per project (default 60 req/min). Excess requests get 429 and are dropped.</li>
                <li style="{{ $liStyle }}"><strong>Retention:</strong> events older than the project's <code style="{{ $kbdStyle }}">event_retention_days</code> (default 30) are deleted daily by <code style="{{ $kbdStyle }}">errors:prune</code>.</li>
                <li style="{{ $liStyle }}"><strong>Troubleshooting:</strong> HTTP 401 from the collector = signature mismatch (check <code style="{{ $kbdStyle }}">ERROR_REPORTER_SECRET</code>). HTTP 404 = unknown token. HTTP 429 = rate-limited.</li>
            </ul>
        </div>

    </div>
</x-filament-panels::page>
