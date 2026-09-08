<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::define('update-post', function (User $user, Article $post) {
            return $user->id === $post->user_id;
        });

        // The task API is consumed by one known client, so the limit is per
        // token rather than per IP — a shared office address must not let one
        // device exhaust another's budget.
        RateLimiter::for('tasks-api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->currentAccessToken()?->id ?? $request->ip()));
    }
}
