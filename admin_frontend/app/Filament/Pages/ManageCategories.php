<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\BuildsApiTablePaginator;
use App\Services\AuthApiClient;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;

class ManageCategories extends Page implements HasTable
{
    use BuildsApiTablePaginator;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $title = 'Categories';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.manage-categories';

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int|string $page, int|string $recordsPerPage): LengthAwarePaginator {
                $perPage = $this->resolvePerPage($recordsPerPage);
                $payload = app(AuthApiClient::class)->getCategories((int) $page, $perPage);

                return $this->apiPaginator($payload, $page, $perPage);
            })
            ->paginated([10, 15, 25, 50])
            ->defaultPaginationPageOption(15)
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
}
