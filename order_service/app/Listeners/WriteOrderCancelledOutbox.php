<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Services\OutboxWriter;

class WriteOrderCancelledOutbox
{
    public function __construct(
        private OutboxWriter $outbox,
    ) {}

    public function handle(OrderCancelled $event): void
    {
        $this->outbox->write('order.events', 'order.cancelled', $event->payload);
    }
}
