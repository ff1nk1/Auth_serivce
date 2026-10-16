<?php

namespace App\Listeners;

use App\Events\StockReserved;
use App\Services\OutboxWriter;

class WriteStockReservedOutbox
{
    public function __construct(
        private OutboxWriter $outbox,
    ) {}

    public function handle(StockReserved $event): void
    {
        $this->outbox->write('stock.events', 'stock.reserved', $event->payload);
    }
}
