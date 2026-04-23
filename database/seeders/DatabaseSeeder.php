<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            ],
        );

        Project::query()->firstOrCreate(
            ['slug' => 'example'],
            [
                'name' => 'Example',
                'token' => Str::random(32),
                'secret' => Str::random(48),
                'event_retention_days' => 30,
                'rate_limit_per_minute' => 60,
                'alert_channels' => null,
            ],
        );
    }
}
