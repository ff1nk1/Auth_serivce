<?php

namespace App\DTO;

readonly class NotificationFilters
{
    public function __construct(
        public ?string $status = null,
        public ?string $email = null,
        public ?string $eventType = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public int $perPage = 20,
    ) {}
}
