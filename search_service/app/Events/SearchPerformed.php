<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SearchPerformed
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public readonly array $params,
        public readonly int $resultsCount,
        public readonly bool $skipAnalytics = false,
    ) {}
}
