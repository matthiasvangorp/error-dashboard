<?php

namespace Database\Factories;

use App\Enums\IssueLevel;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    public function definition(): array
    {
        $now = now();

        return [
            'project_id' => Project::factory(),
            'fingerprint' => hash('sha256', $this->faker->unique()->sentence()),
            'title' => $this->faker->randomElement([
                'QueryException: SQLSTATE[42S02]: Base table or view not found',
                'TypeError: Argument #1 must be of type int, string given',
                'RuntimeException: Unexpected null value',
                'HttpException: 404 Not Found',
            ]),
            'type' => IssueType::Exception,
            'level' => IssueLevel::Error,
            'status' => IssueStatus::Open,
            'environment' => 'production',
            'first_seen_at' => $now->copy()->subDays(2),
            'last_seen_at' => $now,
            'occurrence_count' => $this->faker->numberBetween(1, 50),
        ];
    }

    public function resolved(): self
    {
        return $this->state(['status' => IssueStatus::Resolved]);
    }

    public function log(): self
    {
        return $this->state([
            'type' => IssueType::Log,
            'level' => IssueLevel::Warning,
        ]);
    }
}
