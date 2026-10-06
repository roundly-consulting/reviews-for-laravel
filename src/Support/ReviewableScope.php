<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\MediaLibrary\Support\MediaModel;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\PackageToolkit\Support\RawExpression;
use RoundlyConsulting\Reviews\DataTransferObjects\RatingSummary;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\ReviewsManager;

/**
 * The reviews of one subject — `Reviews::for($product)`.
 *
 * `by($author)` starts a review of this subject, every aggregate counts this subject's
 * **approved, top-level** reviews only (owner responses never count), and `query()` lists this
 * subject's top-level reviews. Nothing here reaches another subject's rows: the subject is fixed
 * at construction and the builder `by()` returns cannot be re-pointed.
 */
final readonly class ReviewableScope
{
    public function __construct(
        private ReviewsManager $manager,
        private Model $reviewable,
    ) {}

    /** Start a review of this subject by `$author`; finish it with `->create()`. */
    public function by(Model $author): PendingReview
    {
        return new PendingReview($this->manager, $this->reviewable, $author);
    }

    /**
     * This subject's top-level reviews (owner responses excluded — reach them through
     * `$review->responses`), every status. Chain the model's scopes:
     * `->approved()->mostHelpful()->paginate()`.
     *
     * @return Builder<Review>
     */
    public function query(): Builder
    {
        return ReviewModel::query()->topLevel()->for($this->reviewable);
    }

    public function average(): ?float
    {
        $average = $this->approved()->avg('rating');

        return $average === null ? null : (float) $average;
    }

    public function count(): int
    {
        return $this->approved()->count();
    }

    /**
     * Approved reviews per rating value, highest rating first.
     *
     * @return array<int, int>
     */
    public function distribution(): array
    {
        $rows = $this->approved()
            ->whereNotNull('rating')
            ->selectRaw('rating, count(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        /** @var array<int, int> $distribution */
        $distribution = [];

        foreach ($rows as $rating => $count) {
            $distribution[(int) $rating] = (int) $count;
        }

        krsort($distribution);

        return $distribution;
    }

    /** Total number of photos across this subject's approved, top-level reviews. */
    public function photoCount(): int
    {
        return $this->photos()?->count() ?? 0;
    }

    /** Number of this subject's approved, top-level reviews that carry at least one photo. */
    public function reviewsWithPhotos(): int
    {
        return $this->photos()?->distinct()->count('model_id') ?? 0;
    }

    public function summary(): RatingSummary
    {
        return new RatingSummary(
            average: $this->average(),
            count: $this->count(),
            distribution: $this->distribution(),
            photoCount: $this->photoCount(),
            reviewsWithPhotos: $this->reviewsWithPhotos(),
        );
    }

    /**
     * The media rows in the photos bucket owned by this subject's approved, top-level reviews,
     * or null when photos are disabled.
     *
     * The review keys stay in the database as a subquery: binding one placeholder per approved
     * review broke past the driver's limit (32 766 on SQLite, 65 535 on PostgreSQL and MySQL).
     * `media.model_id` is a string morph, so the key is cast to a string to compare. Only when
     * the configured models live on different connections are the keys read first.
     *
     * @return Builder<Media>|null
     */
    private function photos(): ?Builder
    {
        if (! Config::boolean('reviews.photos.enabled', true)) {
            return null;
        }

        $review = ReviewModel::new();
        $approved = $this->approved();

        $media = MediaModel::query()
            ->where('bucket_name', ReviewsConfig::photoBucket())
            ->where('model_type', $review->getMorphClass());

        if ($media->getModel()->getConnection() !== $review->getConnection()) {
            return $media->whereIn('model_id', $approved->pluck($review->getKeyName()));
        }

        $key = $approved->getQuery()->getGrammar()->wrap($review->getQualifiedKeyName());
        $string = in_array($review->getConnection()->getDriverName(), ['mysql', 'mariadb'], true) ? 'char' : 'varchar';

        return $media->whereIn('model_id', $approved->select(new RawExpression("cast({$key} as {$string})")));
    }

    /** @return Builder<Review> */
    private function approved(): Builder
    {
        return $this->query()->approved();
    }
}
