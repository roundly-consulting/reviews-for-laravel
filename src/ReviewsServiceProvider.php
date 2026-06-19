<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews;

use Illuminate\Support\ServiceProvider;

final class ReviewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/reviews.php', 'reviews');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/reviews.php' => config_path('reviews.php'),
            ], 'reviews-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'reviews-migrations');
        }
    }
}
