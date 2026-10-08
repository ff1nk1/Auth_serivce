<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\FilePreviewController;
use Livewire\Features\SupportFileUploads\FileUploadController;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Session cookie path is /admin — Livewire endpoints must live under that path
        // or Filament AJAX / uploads lose the session (storefront shares app.localhost).
        Livewire::setUpdateRoute(function ($handle) {
            return Route::post('/admin/livewire/update', $handle)->middleware('web');
        });

        Route::post('/admin/livewire/upload-file', [FileUploadController::class, 'handle'])
            ->middleware('web')
            ->name('livewire.upload-file');

        Route::get('/admin/livewire/preview-file/{filename}', [FilePreviewController::class, 'handle'])
            ->middleware('web')
            ->name('livewire.preview-file');
    }
}
