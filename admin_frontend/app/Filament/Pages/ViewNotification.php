<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AllowsAdminOrAnalyst;
use App\Services\AuthApiClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ViewNotification extends Page
{
    use AllowsAdminOrAnalyst;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'notifications/{record}';

    protected string $view = 'filament.pages.view-notification';

    /** @var array<string, mixed> */
    public array $notification = [];

    public function mount(string $record): void
    {
        $this->loadNotification($record);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Notification details';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resend')
                ->label('Resend')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => ($this->notification['status'] ?? '') === 'failed')
                ->requiresConfirmation()
                ->modalHeading('Resend this email?')
                ->modalDescription('The message will be sent again using the stored payload and template.')
                ->action(function (): void {
                    $id = self::recordIdFrom($this->notification);
                    $response = app(AuthApiClient::class)->resendNotification($id);

                    if ($response->successful()) {
                        FilamentNotification::make()
                            ->title($response->json('message') ?? 'Notification sent')
                            ->success()
                            ->send();
                        $this->loadNotification($id);

                        return;
                    }

                    FilamentNotification::make()
                        ->title($response->json('message') ?? 'Resend failed')
                        ->danger()
                        ->send();
                }),
            Action::make('back')
                ->label('Back to list')
                ->icon('heroicon-o-arrow-left')
                ->url(ManageNotifications::getUrl())
                ->color('gray'),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public static function recordIdFrom(array $record): string
    {
        return (string) ($record['id'] ?? $record['_id'] ?? '');
    }

    protected function loadNotification(string $id): void
    {
        $this->notification = app(AuthApiClient::class)->getNotification($id);
    }
}
