<?php

namespace App\Listeners;

use App\Events\OrderConfirmed;
use App\Services\OutboxWriter;

class WriteOrderConfirmedOutbox
{
    public function __construct(
        private OutboxWriter $outbox,
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $this->outbox->write('order.events', 'order.confirmed', $event->payload);
    }
}
