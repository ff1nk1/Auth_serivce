<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\BuildsApiTablePaginator;
use App\Filament\Concerns\RestrictsToAdmin;
use App\Services\AuthApiClient;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;

class ManageStocks extends Page implements HasTable
{
    use BuildsApiTablePaginator;
    use InteractsWithTable;
    use RestrictsToAdmin;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Stocks';

    protected static ?string $title = 'Stocks';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.manage-stocks';

    public function getHeading(): string|Htmlable
    {
        return 'Stocks';
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int|string $page, int|string $recordsPerPage, ?array $filters): LengthAwarePaginator {
                $perPage = $this->resolvePerPage($recordsPerPage);
                $payload = app(AuthApiClient::class)->getStocks(
                    filters: $this->stockFiltersFromTable($filters),
                    page: (int) $page,
                    perPage: $perPage,
                );

                return $this->apiPaginator($payload, $page, $perPage);
            })
            ->paginated([10, 15, 25, 50])
            ->defaultPaginationPageOption(15)
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder(fn (array $record): string => 'Product #'.($record['product_id'] ?? '—')),
                TextColumn::make('product_id')->label('Product ID'),
                TextColumn::make('store.name')
                    ->label('Store')
                    ->placeholder(fn (array $record): string => 'Store #'.($record['store_id'] ?? '—')),
                TextColumn::make('quantity')->label('Quantity'),
                TextColumn::make('reserved')->label('Reserved'),
                TextColumn::make('available')->label('Available'),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->options(fn (): array => app(AuthApiClient::class)->storeOptions()),
                Filter::make('product_id')
                    ->schema([
                        TextInput::make('product_id')
                            ->label('Product ID')
                            ->numeric(),
                    ])
                    ->indicateUsing(fn (array $state): ?string => filled($state['product_id'] ?? null)
                        ? 'Product ID: '.$state['product_id']
                        : null),
            ], layout: FiltersLayout::AboveContent)
            ->headerActions([
                Action::make('create')
                    ->label('Create')
                    ->form([
                        TextInput::make('product_id')->label('Product ID')->numeric()->required(),
                        Select::make('store_id')
                            ->label('Store')
                            ->options(fn (): array => app(AuthApiClient::class)->storeOptions())
                            ->searchable()
                            ->required(),
                        TextInput::make('quantity')->numeric()->required()->minValue(0),
                    ])
                    ->action(function (array $data): void {
                        app(AuthApiClient::class)->createStock([
                            'product_id' => (int) $data['product_id'],
                            'store_id' => (int) $data['store_id'],
                            'quantity' => (int) $data['quantity'],
                        ]);
                        Notification::make()->title('Stock created')->success()->send();
                        $this->resetTable();
                    }),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit quantity')
                    ->form([
                        TextInput::make('quantity')->numeric()->required()->minValue(0),
                    ])
                    ->fillForm(fn (array $record): array => [
                        'quantity' => $record['quantity'] ?? 0,
                    ])
                    ->action(function (array $data, array $record): void {
                        app(AuthApiClient::class)->updateStock((int) $record['id'], [
                            'quantity' => (int) $data['quantity'],
                        ]);
                        Notification::make()->title('Stock updated')->success()->send();
                        $this->resetTable();
                    }),
            ]);
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return array{store_id?: int, product_id?: int}
     */
    private function stockFiltersFromTable(?array $filters): array
    {
        $filters ??= [];

        return array_filter([
            'store_id' => filled($filters['store_id']['value'] ?? null)
                ? (int) $filters['store_id']['value']
                : null,
            'product_id' => filled($filters['product_id']['product_id'] ?? null)
                ? (int) $filters['product_id']['product_id']
                : null,
        ], fn ($value) => $value !== null);
    }
}
