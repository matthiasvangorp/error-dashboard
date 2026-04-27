@php
    $codeBlockStyle = 'background:#0f172a;color:#e2e8f0;padding:0.75rem 1rem;border-radius:0.375rem;font-size:0.8125rem;line-height:1.55;overflow-x:auto;white-space:pre;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;margin:0.5rem 0 1rem;';
    $sectionStyle = 'margin-bottom:2rem;';
    $headingStyle = 'font-size:1.125rem;font-weight:600;margin-top:1.5rem;margin-bottom:0.5rem;';
    $subHeadingStyle = 'font-size:0.875rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-top:1rem;margin-bottom:0.25rem;';
    $paraStyle = 'margin-bottom:0.75rem;line-height:1.6;';
    $liStyle = 'margin-bottom:0.35rem;';
    $kbdStyle = 'background:rgba(148,163,184,0.2);border:1px solid rgba(148,163,184,0.3);border-radius:0.25rem;padding:0.05rem 0.35rem;font-size:0.8125rem;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;color:inherit;';
    $calloutInfoStyle = 'border-left:3px solid #3b82f6;background:rgba(59,130,246,0.12);padding:0.75rem 1rem;margin:0.75rem 0;font-size:0.875rem;border-radius:0 0.25rem 0.25rem 0;color:inherit;';
    $calloutWarnStyle = 'border-left:3px solid #f59e0b;background:rgba(245,158,11,0.1);padding:0.75rem 1rem;margin:0.75rem 0;font-size:0.875rem;border-radius:0 0.25rem 0.25rem 0;color:inherit;';
    $linkStyle = 'color:#2563eb;text-decoration:underline;';
@endphp

<x-filament-panels::page>
    <div style="max-width:56rem;font-size:0.9375rem;line-height:1.6;">

        <div style="{{ $calloutInfoStyle }}">
            Project <strong>{{ $project->name }}</strong> is ready. Follow the steps below in the Laravel app you want to wire up.
        </div>

        {{-- Step 1 --}}
        <div style="{{ $sectionStyle }}">
            <h2 style="{{ $headingStyle }}">1. Install the client package</h2>
            <p style="{{ $paraStyle }}">In the target Laravel app:</p>
            <pre style="{{ $codeBlockStyle }}">composer require {{ $packageName }}</pre>
            <p style="{{ $paraStyle }}">
                The package is public on
                <a href="{{ $packagistUrl }}" target="_blank" rel="noopener" style="{{ $linkStyle }}">Packagist</a>
                — no auth, no <code style="{{ $kbdStyle }}">repositories</code> block. Service provider auto-discovered. Laravel 10/11/12, PHP 8.2+.
            </p>
        </div>

        {{-- Step 2 --}}
        <div style="{{ $sectionStyle }}">
            <h2 style="{{ $headingStyle }}">2. Append to <code style="{{ $kbdStyle }}">.env</code></h2>
            <p style="{{ $paraStyle }}">These values are filled in for this project. Paste verbatim into the client app's <code style="{{ $kbdStyle }}">.env</code>:</p>
            <pre style="{{ $codeBlockStyle }}"># Error Reporter → {{ $endpoint }}
ERROR_REPORTER_ENABLED=true
ERROR_REPORTER_ENDPOINT={{ $endpoint }}
ERROR_REPORTER_TOKEN={{ $project->token }}
ERROR_REPORTER_SECRET={{ $project->secret }}
ERROR_REPORTER_RELEASE=
ERROR_REPORTER_QUEUE=default
ERROR_REPORTER_LOG_ENABLED=false</pre>
            <div style="{{ $calloutWarnStyle }}">
                The <strong>secret</strong> is shown above for the last time on this page — once you navigate away, it can only be re-displayed by regenerating it (which invalidates the current value). Copy it now into the client's <code style="{{ $kbdStyle }}">.env</code>.
            </div>
            <p style="{{ $paraStyle }}">Add the same block (with blank <code style="{{ $kbdStyle }}">TOKEN</code> / <code style="{{ $kbdStyle }}">SECRET</code>) to <code style="{{ $kbdStyle }}">.env.example</code> for other developers.</p>
            <h3 style="{{ $subHeadingStyle }}">Optional</h3>
            <ul style="padding-left:1.25rem;">
                <li style="{{ $liStyle }}"><code style="{{ $kbdStyle }}">ERROR_REPORTER_RELEASE</code> — commit SHA. Set in your deploy script via <code style="{{ $kbdStyle }}">git rev-parse --short HEAD</code>.</li>
                <li style="{{ $liStyle }}"><code style="{{ $kbdStyle }}">ERROR_REPORTER_QUEUE_CONNECTION</code> — route the ship job to a specific connection (default is whatever <code style="{{ $kbdStyle }}">QUEUE_CONNECTION</code> is).</li>
                <li style="{{ $liStyle }}"><code style="{{ $kbdStyle }}">ERROR_REPORTER_LOG_ENABLED=true</code> — also ship <code style="{{ $kbdStyle }}">Log::error()</code> / <code style="{{ $kbdStyle }}">Log::warning()</code> entries (requires the channel config in step 4).</li>
            </ul>
        </div>

        {{-- Step 3 --}}
        <div style="{{ $sectionStyle }}">
            <h2 style="{{ $headingStyle }}">3. Exception wiring</h2>
            <p style="{{ $paraStyle }}"><strong>Laravel 11/12:</strong> nothing to do — the service provider auto-hooks <code style="{{ $kbdStyle }}">ExceptionHandler::reportable()</code>.</p>
            <p style="{{ $paraStyle }}"><strong>Laravel 10:</strong> only needed if you've overridden <code style="{{ $kbdStyle }}">report()</code> in <code style="{{ $kbdStyle }}">App\Exceptions\Handler</code>. Add the trait:</p>
            <pre style="{{ $codeBlockStyle }}">use MatthiasVanGorp\ErrorReporter\Concerns\ReportsToErrorReporter;

class Handler extends ExceptionHandler
{
    use ReportsToErrorReporter;
    // …
}</pre>
        </div>

        {{-- Step 4 --}}
        <div style="{{ $sectionStyle }}">
            <h2 style="{{ $headingStyle }}">4. (Optional) ship <code style="{{ $kbdStyle }}">Log::*</code> entries too</h2>
            <p style="{{ $paraStyle }}">Off by default. To also collect <code style="{{ $kbdStyle }}">Log::error()</code> / <code style="{{ $kbdStyle }}">Log::warning()</code>:</p>
            <p style="{{ $paraStyle }}">Add the channel in <code style="{{ $kbdStyle }}">config/logging.php</code>:</p>
            <pre style="{{ $codeBlockStyle }}">'channels' => [
    // …
    'error-reporter' => [
        'driver' => 'error-reporter',
        'level' => 'error',
    ],
],</pre>
            <p style="{{ $paraStyle }}">Stack it onto your default channel so log calls fan out:</p>
            <pre style="{{ $codeBlockStyle }}">'stack' => [
    'driver' => 'stack',
    'channels' => ['single', 'error-reporter'],
],</pre>
            <p style="{{ $paraStyle }}">Then flip <code style="{{ $kbdStyle }}">ERROR_REPORTER_LOG_ENABLED=true</code>.</p>
        </div>

        {{-- Step 5 --}}
        <div style="{{ $sectionStyle }}">
            <h2 style="{{ $headingStyle }}">5. Deploy</h2>
            <p style="{{ $paraStyle }}">Commit <code style="{{ $kbdStyle }}">composer.json</code>, <code style="{{ $kbdStyle }}">composer.lock</code>, and <code style="{{ $kbdStyle }}">.env.example</code>. Push.</p>

            <h3 style="{{ $subHeadingStyle }}">On the production server</h3>
            <pre style="{{ $codeBlockStyle }}">cd /var/www/html/your-app
git pull
composer install --no-interaction --optimize-autoloader
# append ERROR_REPORTER_TOKEN + ERROR_REPORTER_SECRET to the server's .env
php artisan config:cache</pre>
        </div>

        {{-- Step 6 --}}
        <div style="{{ $sectionStyle }}">
            <h2 style="{{ $headingStyle }}">6. Verify</h2>
            <p style="{{ $paraStyle }}">From the wired-up app:</p>
            <pre style="{{ $codeBlockStyle }}">php artisan tinker
&gt;&gt;&gt; throw new RuntimeException('error-reporter smoke test from {{ $project->slug ?? $project->name }}');</pre>
            <p style="{{ $paraStyle }}">
                Then refresh the
                <a href="{{ \App\Filament\Resources\IssueResource::getUrl('index') }}" style="{{ $linkStyle }}">Issues list</a>
                — the exception should appear within a few seconds.
            </p>
        </div>

    </div>
</x-filament-panels::page>
