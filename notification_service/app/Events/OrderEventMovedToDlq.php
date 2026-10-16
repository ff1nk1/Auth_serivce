<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Junges\Kafka\Contracts\ConsumerMessage;

class OrderEventMovedToDlq
{
    use Dispatchable;

    public function __construct(
        public readonly ConsumerMessage $message,
        public readonly string $errorMessage,
        public readonly string $email,
    ) {}
}
