<?php

namespace App\Filament\Pages;

use App\Services\AuthApiClient;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class ManageCategories extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $title = 'Categories';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.manage-categories';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->fetchCategories())
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('name')->label('Name'),
                TextColumn::make('slug')->label('Slug'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Create')
                    ->form([
                        TextInput::make('name')->required(),
                        TextInput::make('slug')->required(),
                    ])
                    ->action(function (array $data): void {
                        app(AuthApiClient::class)->createCategory($data);
                        Notification::make()->title('Category created')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->form([
                        TextInput::make('name')->required(),
                        TextInput::make('slug')->required(),
                    ])
                    ->fillForm(fn (array $record): array => [
                        'name' => $record['name'] ?? '',
                        'slug' => $record['slug'] ?? '',
                    ])
                    ->action(function (array $data, array $record): void {
                        app(AuthApiClient::class)->updateCategory((int) $record['id'], $data);
                        Notification::make()->title('Category updated')->success()->send();
                    }),
                Action::make('delete')
                    ->label('Delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (array $record): void {
                        app(AuthApiClient::class)->deleteCategory((int) $record['id']);
                        Notification::make()->title('Category deleted')->success()->send();
                    }),
            ]);
    }

    private function fetchCategories(): Collection
    {
        $payload = app(AuthApiClient::class)->getCategories();
        $rows = $payload['data'] ?? $payload;

        return collect($rows)
            ->map(fn ($row) => is_array($row) ? $row : (array) $row)
            ->keyBy('id');
    }
}
