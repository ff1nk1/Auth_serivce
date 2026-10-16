<?php

namespace App\Providers;

use App\Events\OrderEventMovedToDlq;
use App\Events\UserEventMovedToDlq;
use App\Listeners\PublishOrderEventToDlq;
use App\Listeners\PublishUserEventToDlq;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        OrderEventMovedToDlq::class => [PublishOrderEventToDlq::class],
        UserEventMovedToDlq::class => [PublishUserEventToDlq::class],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
