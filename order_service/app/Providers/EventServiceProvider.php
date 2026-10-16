<?php

namespace App\Providers;

use App\Events\OrderCancelled;
use App\Events\OrderConfirmed;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Events\OrderShipped;
use App\Events\StockReleaseRequested;
use App\Listeners\WriteOrderCancelledOutbox;
use App\Listeners\WriteOrderConfirmedOutbox;
use App\Listeners\WriteOrderCreatedOutbox;
use App\Listeners\WriteOrderPaidOutbox;
use App\Listeners\WriteOrderShippedOutbox;
use App\Listeners\WriteStockReleaseRequestedOutbox;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        OrderCreated::class => [WriteOrderCreatedOutbox::class],
        OrderCancelled::class => [WriteOrderCancelledOutbox::class],
        OrderShipped::class => [WriteOrderShippedOutbox::class],
        OrderPaid::class => [WriteOrderPaidOutbox::class],
        OrderConfirmed::class => [WriteOrderConfirmedOutbox::class],
        StockReleaseRequested::class => [WriteStockReleaseRequestedOutbox::class],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
