<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AllowsAdminOrAnalyst;
use App\Filament\Concerns\BuildsApiTablePaginator;
use App\Services\AuthApiClient;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;

class ManageOrders extends Page implements HasTable
{
    use AllowsAdminOrAnalyst;
    use BuildsApiTablePaginator;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?string $title = 'Orders';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.manage-orders';

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int|string $page, int|string $recordsPerPage): LengthAwarePaginator {
                $perPage = $this->resolvePerPage($recordsPerPage);
                $payload = app(AuthApiClient::class)->getOrders((int) $page, $perPage);

                return $this->apiPaginator($payload, $page, $perPage);
            })
            ->paginated([10, 15, 25, 50])
            ->defaultPaginationPageOption(15)
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('user_id')->label('User ID'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('total_amount')->label('Total'),
                TextColumn::make('created_at')->label('Created')->dateTime(),
            ]);
    }
}
