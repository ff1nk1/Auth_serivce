<?php

namespace App\Listeners;

use App\Events\OrderShipped;
use App\Services\OutboxWriter;

class WriteOrderShippedOutbox
{
    public function __construct(
        private OutboxWriter $outbox,
    ) {}

    public function handle(OrderShipped $event): void
    {
        $this->outbox->write('order.events', 'order.shipped', $event->payload);
    }
}
