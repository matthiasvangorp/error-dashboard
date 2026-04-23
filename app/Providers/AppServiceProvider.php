<?php

namespace App\Providers;

use App\Alerting\AlertManager;
use App\Alerting\Channels\MailChannel;
use App\Alerting\Channels\TelegramChannel;
use App\Models\Project;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AlertManager::class, function ($app) {
            return new AlertManager([
                new TelegramChannel(config('services.telegram.bot_token')),
                new MailChannel(),
            ]);
        });
    }

    public function boot(): void
    {
        RateLimiter::for('ingest', function (Request $request) {
            $project = $this->resolveProject($request);

            if ($project) {
                return Limit::perMinute($project->rate_limit_per_minute)->by('project:'.$project->id);
            }

            return Limit::perMinute(30)->by($request->ip() ?? 'anon');
        });
    }

    private function resolveProject(Request $request): ?Project
    {
        $cached = $request->attributes->get('project');
        if ($cached instanceof Project) {
            return $cached;
        }

        $token = $request->route('project_token');
        if (! $token) {
            return null;
        }

        $project = Project::query()->where('token', $token)->first();
        if ($project) {
            $request->attributes->set('project', $project);
        }

        return $project;
    }
}
