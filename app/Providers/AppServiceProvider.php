<?php

namespace App\Providers;

use App\Enums\IndexType;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

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
        Route::bind('type', static fn (string $value): IndexType => IndexType::fromRouteValue($value));
    }
}
