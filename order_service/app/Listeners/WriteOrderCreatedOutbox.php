<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Services\OutboxWriter;

class WriteOrderCreatedOutbox
{
    public function __construct(
        private OutboxWriter $outbox,
    ) {}

    public function handle(OrderCreated $event): void
    {
        $this->outbox->write('order.events', 'order.created', $event->payload);
    }
}
