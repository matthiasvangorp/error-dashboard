<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $issue = Issue::factory();

        return [
            'issue_id' => $issue,
            'project_id' => function (array $attrs) {
                return Issue::find($attrs['issue_id'])->project_id;
            },
            'payload' => [
                'type' => 'exception',
                'exception' => [
                    'class' => 'RuntimeException',
                    'message' => 'Something broke',
                    'file' => '/var/www/html/app/Services/Foo.php',
                    'line' => 42,
                ],
                'context' => [
                    'environment' => 'production',
                    'url' => 'https://example.test/foo',
                ],
            ],
            'environment' => 'production',
            'release' => null,
            'received_at' => now(),
        ];
    }
}
