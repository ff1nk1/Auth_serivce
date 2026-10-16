<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\OutboxWriter;

class WriteOrderPaidOutbox
{
    public function __construct(
        private OutboxWriter $outbox,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $this->outbox->write('order.events', 'order.paid', $event->payload);
    }
}
