<?php

namespace App\Providers;

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
        // IDs are bigints: "/api/dishes/abc" must be a 404, not a PostgreSQL type error (500).
        Route::patterns([
            'category' => '[0-9]+',
            'dish' => '[0-9]+',
            'table' => '[0-9]+',
            'order' => '[0-9]+',
        ]);
    }
}
