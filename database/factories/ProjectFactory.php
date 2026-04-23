<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.Str::random(4),
            'token' => Str::random(32),
            'secret' => Str::random(48),
            'event_retention_days' => 30,
            'rate_limit_per_minute' => 60,
            'alert_channels' => null,
        ];
    }
}
