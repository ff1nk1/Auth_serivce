<?php

namespace App\Filament\Concerns;

use Illuminate\Pagination\LengthAwarePaginator;

trait BuildsApiTablePaginator
{
    /**
     * Build a LengthAwarePaginator from a Laravel API pagination JSON payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function apiPaginator(array $payload, int|string $page, int|string $recordsPerPage): LengthAwarePaginator
    {
        $perPage = $recordsPerPage === 'all'
            ? max(1, (int) ($payload['total'] ?? 100))
            : max(1, (int) $recordsPerPage);

        $currentPage = max(1, (int) ($payload['current_page'] ?? $page));
        $total = (int) ($payload['total'] ?? 0);
        $rows = $payload['data'] ?? [];

        $items = collect($rows)
            ->map(fn ($row) => is_array($row) ? $row : (array) $row)
            ->keyBy(fn (array $row) => (string) ($row['id'] ?? uniqid('row_', true)));

        return (new LengthAwarePaginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
            options: [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => method_exists($this, 'getTablePaginationPageName')
                    ? $this->getTablePaginationPageName()
                    : 'page',
            ],
        ))->onEachSide(1);
    }

    protected function resolvePerPage(int|string $recordsPerPage, int $fallback = 15): int
    {
        if ($recordsPerPage === 'all') {
            return 100;
        }

        $value = (int) $recordsPerPage;

        return $value > 0 ? $value : $fallback;
    }
}
