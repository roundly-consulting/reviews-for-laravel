<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews;

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\Reviews\Commands\RecountReviewsCommand;
use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Facades\Reviews as ReviewsFacade;
use RoundlyConsulting\Reviews\Listeners\PurgeReviewPhotos;
use RoundlyConsulting\Reviews\Listeners\WarmReviewPhotoVariants;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Moderation\NullModerator;
use RoundlyConsulting\Reviews\Observers\ReviewAggregateObserver;
use RoundlyConsulting\Reviews\Support\ReviewModel;
use RoundlyConsulting\Reviews\Support\ReviewVoteModel;

final class ReviewsServiceProvider extends PackageServiceProvider
{
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
            ->publishesStubs(
                __DIR__.'/Http/Resources',
                app_path('Http/Resources/Reviews'),
                'reviews-resources',
            )
            ->contributesToAbout(fn (): array => $this->aboutPayload());
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(Reviews::class);

        $this->bindFromConfig(ReviewModerator::class, 'reviews.moderator', NullModerator::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ((bool) config('reviews.cache_aggregates', false)) {
            ReviewModel::class()::observe(ReviewAggregateObserver::class);
        }

        if ((bool) config('reviews.photos.enabled', true)) {
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
        $bannedWords = config('reviews.moderation.banned_words', []);
        $bannedWords = is_array($bannedWords) ? $bannedWords : [];

        $disk = config('reviews.photos.disk');
        $photosEnabled = (bool) config('reviews.photos.enabled', true);

        return [
            'Review model' => ReviewModel::class(),
            'Vote model' => ReviewVoteModel::class(),
            'Rating scale' => sprintf(
                '%d-%d',
                (int) config('reviews.min_rating', 1),
                (int) config('reviews.max_rating', 5),
            ),
            'Default status' => (string) config('reviews.default_status', 'pending'),
            'Auto approve' => $this->switch((bool) config('reviews.auto_approve', false)),
            'Reset status on edit' => $this->switch((bool) config('reviews.reset_status_on_edit', true)),
            'One review per author' => $this->switch((bool) config('reviews.one_per_author', false)),
            'Moderator' => class_basename((string) config('reviews.moderator', NullModerator::class)),
            'Banned words' => $bannedWords === [] ? 'NONE' : sprintf('%d term(s)', count($bannedWords)),
            'Cached aggregates' => $this->switch((bool) config('reviews.cache_aggregates', false)),
            'Facade alias' => $this->aliasLabel(),
            'Photos' => $this->switch($photosEnabled),
            'Photo limits' => $photosEnabled ? $this->photoLimits() : 'N/A',
            'Photo disk' => is_string($disk) && $disk !== '' ? 'SET' : 'DEFAULT',
            'Photo visibility' => config('reviews.photos.visibility', 'public') === 'private' ? 'private' : 'public',
            'Warm variants on approval' => $this->switch((bool) config('reviews.photos.warm_on_approval', true)),
        ];
    }

    private function photoLimits(): string
    {
        $max = (int) config('reviews.photos.max', 5);
        $size = (int) config('reviews.photos.max_file_size', 0);

        return sprintf(
            '%s, %s',
            $max > 0 ? $max.' per review' : 'unlimited',
            $size > 0 ? $size.' B each' : 'no size cap',
        );
    }

    private function aliasLabel(): string
    {
        $configured = config('reviews.register_facade_alias', true);

        if ($configured === false || $configured === null || $configured === '') {
            return 'DISABLED';
        }

        return is_string($configured) ? $configured : 'Reviews';
    }

    private function switch(bool $enabled): string
    {
        return $enabled ? 'ON' : 'OFF';
    }
}
