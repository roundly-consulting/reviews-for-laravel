<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews;

use Closure;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Reviews\Commands\RecountReviewsCommand;
use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Facades\Reviews as ReviewsFacade;
use RoundlyConsulting\Reviews\Listeners\PurgeReviewPhotos;
use RoundlyConsulting\Reviews\Listeners\WarmReviewPhotoVariants;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Moderation\NullModerator;
use RoundlyConsulting\Reviews\Observers\ReviewAggregateObserver;
use RoundlyConsulting\Reviews\Support\ReviewModel;
use RoundlyConsulting\Reviews\Support\ReviewsConfig;
use RoundlyConsulting\Reviews\Support\ReviewVoteModel;

final class ReviewsServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('reviews')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasTranslations()
            ->hasCommands([RecountReviewsCommand::class])
            ->hasFacadeAlias(ReviewsFacade::class, 'reviews.register_facade_alias')
            // The aggregate migration is an opt-in STUB, not one of the package's own migrations:
            // the host renames the placeholder table inside it and publishes it once per reviewable
            // table that opts into cached aggregates. It therefore keeps its own
            // `reviews-aggregate-migrations` tag (folding it into `reviews-migrations` would push a
            // placeholder migration onto every host) and its own freshly stamped destination, so a
            // second publish yields a second file for the second table.
            ->publishesStubs(
                __DIR__.'/../database/migrations/stubs/add_review_aggregates_to_reviewable_table.php.stub',
                database_path('migrations/'.date('Y_m_d_His').'_add_review_aggregates_to_reviewable_table.php'),
                'reviews-aggregate-migrations',
            )
            // Host-owned copies in the App\Http\Resources\Reviews namespace — never the package's
            // own classes, whose namespace would break PSR-4 under app/ and never load.
            ->publishesStubs(
                __DIR__.'/../stubs/ReviewResource.php.stub',
                app_path('Http/Resources/Reviews/ReviewResource.php'),
                'reviews-resources',
            )
            ->publishesStubs(
                __DIR__.'/../stubs/ReviewCollection.php.stub',
                app_path('Http/Resources/Reviews/ReviewCollection.php'),
                'reviews-resources',
            )
            ->contributesToAbout(fn (): array => $this->aboutPayload());
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ReviewsManager::class);

        $this->bindFromConfig(ReviewModerator::class, 'reviews.moderator', NullModerator::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The migrations' key-type-aware morph columns are macros, so they must
        // exist before a host runs `php artisan migrate`.
        $this->registerBlueprintMacros();

        if (Config::boolean('reviews.cache_aggregates')) {
            ReviewModel::class()::observe(ReviewAggregateObserver::class);
        }

        if (Config::boolean('reviews.photos.enabled', true)) {
            Event::listen(ReviewApproved::class, WarmReviewPhotoVariants::class);

            ReviewModel::class()::forceDeleted(static function (Review $review): void {
                app(PurgeReviewPhotos::class)->handle($review);
            });
        }
    }

    /**
     * The `php artisan about --only=reviews` payload.
     *
     * Switches, bounds and presence only. The moderation blocklist renders as a **count** —
     * printing the terms would hand anyone reading the output the exact word list the filter
     * screens for — and the photos disk as SET/DEFAULT rather than by name.
     *
     * @return array<string, string>
     */
    private function aboutPayload(): array
    {
        $photosEnabled = Config::boolean('reviews.photos.enabled', true);

        return [
            'Review model' => ReviewModel::class(),
            'Vote model' => ReviewVoteModel::class(),
            'Rating scale' => $this->orInvalid(static fn (): string => implode('-', ReviewsConfig::ratingRange())),
            'Default status' => $this->orInvalid(
                static fn (): string => Config::enum('reviews.default_status', ReviewStatus::class, ReviewStatus::Pending)->value,
            ),
            'Auto approve' => $this->switch(Config::boolean('reviews.auto_approve')),
            'Reset status on edit' => $this->switch(Config::boolean('reviews.reset_status_on_edit', true)),
            'One review per author' => $this->switch(Config::boolean('reviews.one_per_author')),
            'Moderator' => class_basename((string) config('reviews.moderator', NullModerator::class)),
            'Banned words' => $this->orInvalid(static function (): string {
                $count = count(ReviewsConfig::bannedWords());

                return $count === 0 ? 'NONE' : sprintf('%d term(s)', $count);
            }),
            'Cached aggregates' => $this->switch(Config::boolean('reviews.cache_aggregates')),
            'Facade alias' => $this->aliasLabel(),
            'Photos' => $this->switch($photosEnabled),
            'Photo limits' => $photosEnabled ? $this->orInvalid($this->photoLimits(...)) : 'N/A',
            'Photo disk' => $this->orInvalid(static fn (): string => ReviewsConfig::photoDisk() === null ? 'DEFAULT' : 'SET'),
            'Photo visibility' => $this->orInvalid(ReviewsConfig::photoVisibility(...)),
            'Warm variants on approval' => $this->switch(Config::boolean('reviews.photos.warm_on_approval', true)),
        ];
    }

    private function photoLimits(): string
    {
        $max = ReviewsConfig::photoLimit();
        $size = ReviewsConfig::photoMaxFileSize();

        return sprintf(
            '%s, %s',
            $max > 0 ? $max.' per review' : 'unlimited',
            $size > 0 ? $size.' B each' : 'no size cap',
        );
    }

    /**
     * Mirrors the toolkit's alias resolution: `null` or a false spelling disables it, a
     * string that is not a boolean word is the alias name, and a junk value throws.
     */
    private function aliasLabel(): string
    {
        $configured = config('reviews.register_facade_alias', true);

        if ($configured === null) {
            return 'DISABLED';
        }

        if (is_string($configured) && filter_var($configured, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === null) {
            return $configured;
        }

        return Config::boolean('reviews.register_facade_alias', true) ? 'Reviews' : 'DISABLED';
    }

    /**
     * A strict read rendered for `about`, or `INVALID` when the setting is broken — so
     * `php artisan about` still works on a misconfigured host while every real read throws.
     *
     * @param  Closure(): string  $read
     */
    private function orInvalid(Closure $read): string
    {
        try {
            return $read();
        } catch (InvalidConfigurationException) {
            return 'INVALID';
        }
    }

    private function switch(bool $enabled): string
    {
        return $enabled ? 'ON' : 'OFF';
    }
}
