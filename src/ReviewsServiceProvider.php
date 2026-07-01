<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Reviews\Commands\RecountReviewsCommand;
use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Facades\Reviews as ReviewsFacade;
use RoundlyConsulting\Reviews\Listeners\PurgeReviewPhotos;
use RoundlyConsulting\Reviews\Listeners\WarmReviewPhotoVariants;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Moderation\NullModerator;
use RoundlyConsulting\Reviews\Observers\ReviewAggregateObserver;

final class ReviewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/reviews.php', 'reviews');

        $this->app->singleton(Reviews::class);

        $this->app->bind(ReviewModerator::class, function (): ReviewModerator {
            /** @var class-string<ReviewModerator> $moderator */
            $moderator = config('reviews.moderator', NullModerator::class);

            return $this->app->make($moderator);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'reviews');

        if ((bool) config('reviews.register_facade_alias', true)) {
            AliasLoader::getInstance()->alias('Reviews', ReviewsFacade::class);
        }

        if ((bool) config('reviews.cache_aggregates', false)) {
            /** @var class-string<Review> $model */
            $model = config('reviews.model', Review::class);
            $model::observe(ReviewAggregateObserver::class);
        }

        if ((bool) config('reviews.photos.enabled', true)) {
            Event::listen(ReviewApproved::class, WarmReviewPhotoVariants::class);

            /** @var class-string<Review> $reviewModel */
            $reviewModel = config('reviews.model', Review::class);

            $reviewModel::forceDeleted(static function (Review $review): void {
                app(PurgeReviewPhotos::class)->handle($review);
            });
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                RecountReviewsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/reviews.php' => config_path('reviews.php'),
            ], 'reviews-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'reviews-migrations');

            $this->publishes([
                __DIR__.'/../database/migrations/stubs/add_review_aggregates_to_reviewable_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_add_review_aggregates_to_reviewable_table.php'),
            ], 'reviews-aggregate-migrations');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/reviews'),
            ], 'reviews-translations');

            $this->publishes([
                __DIR__.'/../src/Http/Resources' => app_path('Http/Resources/Reviews'),
            ], 'reviews-resources');
        }
    }
}
