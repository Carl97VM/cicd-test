<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        Log::withContext([
            'release' => trim(@file_get_contents(base_path('RELEASE_ID'))) ?: 'local',
            'env' => config('app.env'),
            'user_id' => Auth::id() ?? 'guest',
        ]);
    }
}
