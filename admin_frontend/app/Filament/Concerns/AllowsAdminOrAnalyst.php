<?php

namespace App\Filament\Concerns;

trait AllowsAdminOrAnalyst
{
    public static function canAccess(array $parameters = []): bool
    {
        return in_array(auth()->user()?->role_slug, ['admin', 'analyst'], true);
    }
}
