<?php

namespace App\Kafka\Handlers;

use App\Mail\WelcomeMail;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;

class UserEventsHandler
{
    private const MAX_RETRIES = 3;         // Максимальное кол-во попыток
    private const INITIAL_BACKOFF_SEC = 1;  // Базовая задержка в секундах

    public function __invoke(ConsumerMessage $message): void
    {
        $body = $message->getBody();
        $key = $message->getKey();
        $emailAddress = $body['email'] ?? null;

        if (!$emailAddress) {
            Log::warning('Kafka: Сообщение пропущено, отсутствует email', ['body' => $body]);
            return;
        }

        $emailLog = EmailLog::create([
            'name'         => $body['name'] ?? null,
            'email'        => $emailAddress,
            'status'       => 'pending',
            'retries'      => 0,
            'moved_to_dlq' => false,
            'payload'      => $body,
        ]);

        $attempt = 0;
        $isSent = false;
        $lastError = '';

        //Цикл ретраев с экспоненциальной задержкой
        while ($attempt < self::MAX_RETRIES && !$isSent) {
            $attempt++;

            try {
                Mail::to($emailAddress)->send(new WelcomeMail($body));

                // Успешная отправка
                $emailLog->update([
                    'status'  => 'sent',
                    'retries' => $attempt,
                    'time'    => time(),
                ]);
                
                $isSent = true;
                echo "\n[KAFKA УСПЕХ] Письмо отправлено на {$emailAddress} с попытки {$attempt}\n";

            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $emailLog->update(['retries' => $attempt]);

                if ($attempt < self::MAX_RETRIES) {
                    // Формула backoff: 1s * 2^(attempt-1) -> 1s, 2s, 4s...
                    $sleepTime = self::INITIAL_BACKOFF_SEC * (2 ** ($attempt - 1));

                    Log::warning("SMTP Сбой (Попытка {$attempt}/" . self::MAX_RETRIES . "). Ждем {$sleepTime} сек...", [
                        'email' => $emailAddress,
                        'error' => $lastError,
                    ]);

                    sleep($sleepTime); // Приостанавливаем выполнение
                }
            }
        }

        // 3. Если все попытки исчерпаны — отправляем в DLQ
        if (!$isSent) {
            $this->sendToDlq($message, $emailLog, $lastError);
        }
    }

    /**
     * Пересылка необработанного сообщения в DLQ топик
     */
    private function sendToDlq(ConsumerMessage $message, EmailLog $emailLog, string $errorMessage): void
    {
        // Помечаем документ в MongoDB
        $emailLog->update([
            'status'        => 'failed',
            'moved_to_dlq'  => true,
            'error_message' => $errorMessage,
        ]);

        try {
            Kafka::publish(config('kafka.brokers', 'kafka:29092'))
                ->onTopic('user.events.dlq')
                ->withKafkaKey((string) $message->getKey())
                ->withHeaders(array_merge($message->getHeaders() ?? [], [
                    'dlq-reason' => $errorMessage,
                    'dlq-failed-at' => now()->toIso8601String(),
                ]))
                ->withBody($message->getBody())
                ->send();

            Log::error("Сообщение не обработано и перенаправлено в DLQ (user.events.dlq)", [
                'email' => $emailLog->email,
                'error' => $errorMessage,
            ]);

            echo "\n[KAFKA DLQ] Сообщение для {$emailLog->email} отправлено в DLQ топик!\n";

        } catch (\Throwable $e) {
            Log::critical('Не удалось опубликовать сообщение в DLQ топик: ' . $e->getMessage());
        }
    }
}