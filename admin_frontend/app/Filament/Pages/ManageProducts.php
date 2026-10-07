<?php

namespace App\Filament\Pages;

use App\Services\AuthApiClient;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class ManageProducts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Products';

    protected static ?string $title = 'Products';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.manage-products';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->fetchProducts())
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('name')->label('Name'),
                TextColumn::make('price')->label('Price'),
                TextColumn::make('category_id')->label('Category'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Create')
                    ->form($this->productForm())
                    ->action(function (array $data): void {
                        app(AuthApiClient::class)->createProduct($data);
                        Notification::make()->title('Product created')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->form($this->productForm())
                    ->fillForm(fn (array $record): array => [
                        'name' => $record['name'] ?? '',
                        'price' => $record['price'] ?? '',
                        'description' => $record['description'] ?? '',
                        'category_id' => $record['category_id'] ?? null,
                    ])
                    ->action(function (array $data, array $record): void {
                        app(AuthApiClient::class)->updateProduct((int) $record['id'], $data);
                        Notification::make()->title('Product updated')->success()->send();
                    }),
                Action::make('delete')
                    ->label('Delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (array $record): void {
                        app(AuthApiClient::class)->deleteProduct((int) $record['id']);
                        Notification::make()->title('Product deleted')->success()->send();
                    }),
            ]);
    }

    private function productForm(): array
    {
        return [
            TextInput::make('name')->required(),
            TextInput::make('price')->numeric()->required(),
            TextInput::make('category_id')->numeric()->required(),
            Textarea::make('description'),
        ];
    }

    private function fetchProducts(): Collection
    {
        $payload = app(AuthApiClient::class)->getProducts();
        $rows = $payload['data'] ?? $payload;

        return collect($rows)
            ->map(fn ($row) => is_array($row) ? $row : (array) $row)
            ->keyBy('id');
    }
}
