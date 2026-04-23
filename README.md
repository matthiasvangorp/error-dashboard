# Error Dashboard

A self-hosted Laravel app that receives, stores, and displays errors and logs shipped from other Laravel projects. Role is narrower than Sentry: no APM, no source maps, no release tracking — just deduped issues with alerting.

A companion Composer package (`matthiasvangorp/error-reporter`) is installed in each client Laravel app and posts HMAC-signed events to `POST /api/ingest/{token}` here.

## Requirements

- PHP 8.2+
- MySQL 8 or MariaDB 11
- Composer
- A queue worker (database driver — no Redis required)
- A scheduler cron entry
- HTTPS for the ingest endpoint

Host-agnostic: deploy on any VPS, RunCloud, Linode, Cloudways, Azure, etc.

## Core concepts

- **Projects** — one row per client Laravel app. Has a `token` (path segment) and a `secret` (HMAC signing key).
- **Issues** — deduped error groups, keyed by `sha256(fingerprint)` per project. Exceptions fingerprint on `class + normalized_file_path + line`; logs on `channel + level + templatized_message` (numbers, UUIDs, quoted strings replaced).
- **Events** — raw payloads attached to an issue.

## Endpoints

- `POST /api/ingest/{project_token}` — accepts JSON payload. `X-Signature: sha256=<hmac_sha256(body, project.secret)>` header required. Returns `202 Accepted` with `{issue_id, event_id}`.
- `/admin` — Filament panel (single admin account).

## Alerting

One alert per signal-to-noise trigger:
- New issue (first occurrence of a fingerprint).
- Reopen — issue was `resolved` and a new event arrived.

Subsequent occurrences of an open issue increment the counter silently. Alert channels dispatch on the `alerts` queue via `DispatchAlertJob`.

### Channels (v1)
- Telegram — set `TELEGRAM_BOT_TOKEN` in `.env`; per-project `chat_id` stored in `projects.alert_channels`.
- Email — per-project recipient list in `projects.alert_channels.email`.

Add new channels by implementing `App\Alerting\AlertChannel` and registering the instance in `AppServiceProvider`.

## Deployment

### Bootstrap

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force       # seeds admin user + "Example" project
php artisan storage:link
php artisan config:cache route:cache view:cache
```

Set at minimum in `.env`:
- `APP_URL=https://errors.example.com`
- `APP_ENV=production`, `APP_DEBUG=false`
- DB credentials
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database` (or `file` if the DB is tight)
- `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME` — consumed by `DatabaseSeeder`
- `TELEGRAM_BOT_TOKEN` — optional
- `TRUSTED_PROXIES=<your_proxy_ip_range>` in production (use `*` only in dev)

### Queue worker (systemd)

Dedicated queue `alerts` for alert jobs so they don't queue behind anything else.

```ini
# /etc/systemd/system/error-dashboard-worker.service
[Unit]
Description=Error Dashboard queue worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/error-dashboard
ExecStart=/usr/bin/php artisan queue:work --queue=alerts,default --tries=3 --sleep=3 --timeout=60
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now error-dashboard-worker
```

Ingest itself is synchronous — the controller writes issue + event directly. Only alerting is queued.

### Scheduler

```cron
* * * * * cd /var/www/error-dashboard && php artisan schedule:run >> /dev/null 2>&1
```

The scheduled commands are defined in `routes/console.php`:
- `errors:prune` daily at 03:15 — drops events older than `project.event_retention_days` and resolved issues with no events for 90 days.

### Ingest HTTPS

The `X-Signature` HMAC protects payload integrity but not confidentiality. Always terminate TLS in front of Laravel (nginx/Caddy/Traefik/whatever). The app honors `TRUSTED_PROXIES` so it issues `https://` URLs behind a reverse proxy.

### Regenerating secrets

Filament → Projects → "Regenerate secret" action. The old secret is invalidated immediately; update the client package config in the target project.

## Local development

The repo ships with a Docker stack (Apache + PHP 8.2 + MySQL + Mailpit + Redis) behind Traefik.

```bash
cp .env.example .env
docker-compose up -d --build
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate --seed
```

Admin: https://errors.test/admin (credentials from `.env` `ADMIN_EMAIL` / `ADMIN_PASSWORD`).
Mail: https://mail.errors.test (Mailpit catches everything).

Run the queue worker:
```bash
docker-compose exec app php artisan queue:work --queue=alerts,default
```

## Testing

```bash
docker-compose exec app php artisan test
```

Feature coverage (see `tests/Feature/IngestTest.php`):
- Valid ingest creates issue + event + alert
- Invalid HMAC → 401
- Unknown token → 404
- Duplicate fingerprint increments counter, no duplicate alert
- Resolving + re-ingesting triggers a reopen alert
- Per-project rate limit enforced
- Log events group by templatized message

## Non-goals

- No performance / APM tracking
- No release tracking or source maps
- No multi-tenant user accounts — single admin
- No web-based project onboarding — create projects via Filament
