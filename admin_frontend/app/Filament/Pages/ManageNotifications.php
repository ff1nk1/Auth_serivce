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

class ManageNotifications extends Page implements HasTable
{
    use AllowsAdminOrAnalyst;
    use BuildsApiTablePaginator;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?string $title = 'Notifications';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.manage-notifications';

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int|string $page, int|string $recordsPerPage): LengthAwarePaginator {
                $perPage = $this->resolvePerPage($recordsPerPage);
                $payload = app(AuthApiClient::class)->getNotifications([], (int) $page, $perPage);

                return $this->apiPaginator($payload, $page, $perPage);
            })
            ->paginated([10, 15, 25, 50])
            ->defaultPaginationPageOption(15)
            ->columns([
                TextColumn::make('id')->label('ID')->limit(12),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('name')->label('Template')->limit(40),
                TextColumn::make('error_message')->label('Error')->limit(40),
                TextColumn::make('time')->label('Time'),
            ]);
    }
}
