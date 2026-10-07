<?php

namespace Database\Factories;

use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailLogFactory extends Factory
{
    /**
     * Имя связанной с фабрикой модели.
     *
     * @var string
     */
    protected $model = EmailLog::class;

    /**
     * Определить состояние модели по умолчанию.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = $this->faker->unique()->safeEmail();
        $name = $this->faker->name();

        return [
            'message_id'    => $this->faker->uuid(),
            'email'         => $email,
            'name'          => $name,
            'payload'       => [
                'email' => $email,
                'name'  => $name,
            ],
            'status'        => 'pending',
            'retries'       => 0,
            'moved_to_dlq'  => false,
            'error_message' => null,
        ];
    }

    /**
     * Состояние: Письмо успешно отправлено.
     */
    public function sent(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status'  => 'sent',
            'retries' => 1,
        ]);
    }

    /**
     * Состояние: Отправка завершилась ошибкой.
     */
    public function failed(string $errorMessage = 'Connection timeout'): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status'        => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Состояние: Сообщение перемещено в Dead Letter Queue (DLQ).
     */
    public function dlq(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status'        => 'failed',
            'moved_to_dlq'  => true,
            'retries'       => 3, // например, максимальное число попыток
            'error_message' => 'Max retries exhausted',
        ]);
    }
}