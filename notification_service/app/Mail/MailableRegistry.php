<?php

namespace App\Mail;

use Illuminate\Contracts\Mail\Mailable;
use InvalidArgumentException;

class MailableRegistry
{
    /**
     * @var array<string, class-string<Mailable>>
     */
    private const MAP = [
        'order.paid' => OrderPaidMail::class,
        'order.confirmed' => OrderConfirmedMail::class,
        'order.cancelled' => OrderCancelledMail::class,
        'user.registered' => WelcomeMail::class,
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function make(string $eventType, array $payload): Mailable
    {
        if ($eventType === '' || ! isset(self::MAP[$eventType])) {
            throw new InvalidArgumentException("Unknown notification event_type: {$eventType}");
        }

        $class = self::MAP[$eventType];

        return new $class($payload);
    }

    /**
     * @return list<string>
     */
    public function knownEventTypes(): array
    {
        return array_keys(self::MAP);
    }

    public function supports(string $eventType): bool
    {
        return isset(self::MAP[$eventType]);
    }
}
