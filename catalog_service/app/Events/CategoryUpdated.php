<?php

namespace App\Events;

use App\Models\Category;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CategoryUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Category $category,
    ) {}

    public function topic(): string
    {
        return 'category.updated';
    }
}
