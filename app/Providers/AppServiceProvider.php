<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Each user action costs three requests (the action itself, then a list and a stats refresh),
        // so the ceiling has to stay well clear of normal clicking.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
    }
}
