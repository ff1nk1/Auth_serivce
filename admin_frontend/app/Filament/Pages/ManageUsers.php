<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\BuildsApiTablePaginator;
use App\Filament\Concerns\RestrictsToAdmin;
use App\Services\AuthApiClient;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;

class ManageUsers extends Page implements HasTable
{
    use BuildsApiTablePaginator;
    use InteractsWithTable;
    use RestrictsToAdmin;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $title = 'Users';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.manage-users';

    public function getHeading(): string|Htmlable
    {
        return 'Users';
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int|string $page, int|string $recordsPerPage): LengthAwarePaginator {
                $perPage = $this->resolvePerPage($recordsPerPage);
                $payload = app(AuthApiClient::class)->getUsers(page: (int) $page, perPage: $perPage);

                return $this->apiPaginator($payload, $page, $perPage);
            })
            ->paginated([10, 15, 25, 50])
            ->defaultPaginationPageOption(15)
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('name')->label('Name')->searchable(),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('role.name')->label('Role'),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('changeRole')
                    ->label('Change role')
                    ->form([
                        Select::make('role_id')
                            ->label('Role')
                            ->options(fn () => collect(app(AuthApiClient::class)->getRoles())
                                ->mapWithKeys(fn ($r) => [$r['id'] => $r['name'] ?? $r['slug']]))
                            ->required(),
                    ])
                    ->action(function (array $data, array $record): void {
                        app(AuthApiClient::class)->changeUserRole((int) $record['id'], (int) $data['role_id']);
                        Notification::make()->title('Role updated')->success()->send();
                    }),
            ]);
    }
}
