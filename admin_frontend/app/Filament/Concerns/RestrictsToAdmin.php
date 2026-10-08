<?php

namespace App\Filament\Concerns;

trait RestrictsToAdmin
{
    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->role_slug === 'admin';
    }
}
