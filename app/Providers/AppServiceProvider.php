<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register application services here.
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        // Browsers on the same classroom/event network must not share a polling quota.
        RateLimiter::for('quiz-state', fn (Request $request) => Limit::perMinute(180)
            ->by($request->session()->getId()));
        RateLimiter::for('quiz-answer', fn (Request $request) => Limit::perMinute(120)
            ->by($request->session()->getId()));
        RateLimiter::for('quiz-join', fn (Request $request) => Limit::perMinute(10)
            ->by($request->session()->getId()));
    }
}
