<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\BuildsApiTablePaginator;
use App\Services\AuthApiClient;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageProducts extends Page implements HasTable
{
    use BuildsApiTablePaginator;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Products';

    protected static ?string $title = 'Products';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.manage-products';

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int|string $page, int|string $recordsPerPage): LengthAwarePaginator {
                $perPage = $this->resolvePerPage($recordsPerPage);
                $payload = app(AuthApiClient::class)->getProducts((int) $page, $perPage);

                return $this->apiPaginator($payload, $page, $perPage);
            })
            ->paginated([10, 15, 25, 50])
            ->defaultPaginationPageOption(15)
            ->columns([
                TextColumn::make('id')->label('ID'),
                ImageColumn::make('image_url')
                    ->label('Image')
                    ->getStateUsing(fn (array $record): ?string => $this->publicImageUrl($record['image_url'] ?? null))
                    ->height(48)
                    ->extraImgAttributes(['loading' => 'lazy']),
                TextColumn::make('name')->label('Name'),
                TextColumn::make('price')->label('Price'),
                TextColumn::make('store_id')->label('Store'),
                TextColumn::make('category_id')->label('Category'),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Create')
                    ->form($this->productForm())
                    ->action(function (array $data): void {
                        $payload = $this->prepareProductPayload($data);
                        app(AuthApiClient::class)->createProduct($payload);
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
                        'store_id' => $record['store_id'] ?? null,
                        'image_url' => $record['image_url'] ?? null,
                    ])
                    ->action(function (array $data, array $record): void {
                        $payload = $this->prepareProductPayload($data, $record['image_url'] ?? null);
                        app(AuthApiClient::class)->updateProduct((int) $record['id'], $payload);
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
            TextInput::make('store_id')->numeric()->required()->label('Store ID'),
            TextInput::make('category_id')->numeric()->required()->label('Category ID'),
            Textarea::make('description'),
            FileUpload::make('image')
                ->label('Photo')
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                ->maxSize(5120)
                ->storeFiles(false)
                ->nullable(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareProductPayload(array $data, ?string $existingImageUrl = null): array
    {
        $image = $data['image'] ?? null;
        unset($data['image'], $data['image_url']);

        if ($image instanceof TemporaryUploadedFile) {
            $data['image_url'] = app(AuthApiClient::class)->uploadProductImage(
                $image->getClientOriginalName(),
                $image->getMimeType() ?: 'application/octet-stream',
                file_get_contents($image->getRealPath())
            );
        } elseif (is_array($image) && isset($image[0]) && $image[0] instanceof TemporaryUploadedFile) {
            $file = $image[0];
            $data['image_url'] = app(AuthApiClient::class)->uploadProductImage(
                $file->getClientOriginalName(),
                $file->getMimeType() ?: 'application/octet-stream',
                file_get_contents($file->getRealPath())
            );
        } elseif (is_string($existingImageUrl) && $existingImageUrl !== '') {
            $data['image_url'] = $existingImageUrl;
        }

        return $data;
    }

    private function publicImageUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $base = rtrim((string) config('app.url'), '/');
        $path = str_starts_with($url, '/') ? $url : '/'.$url;

        return $base.$path;
    }
}
