<?php

namespace App\Listeners;

use App\Events\StockReleaseRequested;
use App\Services\OutboxWriter;

class WriteStockReleaseRequestedOutbox
{
    public function __construct(
        private OutboxWriter $outbox,
    ) {}

    public function handle(StockReleaseRequested $event): void
    {
        $this->outbox->write('order.events', 'stock.release_requested', $event->payload);
    }
}
