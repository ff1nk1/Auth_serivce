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

        // Livewire registers upload/preview under /livewire-{hash}/ during provider boot.
        // Laravel's RouteServiceProvider later calls refreshNameLookups() (first-wins), so we
        // re-bind under /admin and force the name lookup in a nested booted callback that
        // runs after that refresh — signed URLs then keep the session cookie.
        $this->app->booted(function () {
            Route::post('/admin/livewire/upload-file', [FileUploadController::class, 'handle'])
                ->middleware('web')
                ->name('livewire.upload-file');

            Route::get('/admin/livewire/preview-file/{filename}', [FilePreviewController::class, 'handle'])
                ->middleware('web')
                ->name('livewire.preview-file');

            $this->app->booted(function () {
                $routes = Route::getRoutes();
                $upload = collect($routes->getRoutes())
                    ->first(fn ($route) => $route->uri() === 'admin/livewire/upload-file');
                $preview = collect($routes->getRoutes())
                    ->first(fn ($route) => $route->uri() === 'admin/livewire/preview-file/{filename}');

                (function () use ($upload, $preview) {
                    $this->nameList['livewire.upload-file'] = $upload;
                    $this->nameList['livewire.preview-file'] = $preview;
                })->call($routes);
            });
        });
    }
}
