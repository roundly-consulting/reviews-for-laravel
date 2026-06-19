<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Reviews\Facades\Reviews as ReviewsFacade;

final class ReviewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/reviews.php', 'reviews');

        $this->app->singleton(Reviews::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'reviews');

        if ((bool) config('reviews.register_facade_alias', true)) {
            AliasLoader::getInstance()->alias('Reviews', ReviewsFacade::class);
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/reviews.php' => config_path('reviews.php'),
            ], 'reviews-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'reviews-migrations');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/reviews'),
            ], 'reviews-translations');
        }
    }
}
