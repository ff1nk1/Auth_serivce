<?php

namespace Database\Factories;

use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLog>
 */
class EmailLogFactory extends Factory
{
    protected $model = EmailLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = $this->faker->unique()->safeEmail();
        $name = $this->faker->name();

        return [
            'message_id' => $this->faker->uuid(),
            'email' => $email,
            'name' => $name,
            'payload' => [
                'email' => $email,
                'name' => $name,
            ],
            'status' => 'pending',
            'retries' => 0,
            'moved_to_dlq' => false,
            'error_message' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'retries' => 1,
        ]);
    }

    public function failed(string $errorMessage = 'Connection timeout'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    public function dlq(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'moved_to_dlq' => true,
            'retries' => 3,
            'error_message' => 'Max retries exhausted',
        ]);
    }
}
