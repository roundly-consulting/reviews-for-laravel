<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Reviews\DataTransferObjects\RatingSummary;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Reviews;
use RoundlyConsulting\Reviews\Support\PendingReview;

/**
 * Adds review capability to a subject model: relations, aggregates, and a
 * convenience builder. All aggregates count approved reviews only.
 *
 * @phpstan-require-extends Model
 */
trait HasReviews
{
    /**
     * @return MorphMany<Review, $this>
     */
    public function reviews(): MorphMany
    {
        /** @var class-string<Review> $model */
        $model = config('reviews.model', Review::class);

        return $this->morphMany($model, 'reviewable');
    }

    /**
     * Top-level reviews only (excludes owner responses).
     *
     * @return MorphMany<Review, $this>
     */
    public function topLevelReviews(): MorphMany
    {
        return $this->reviews()->topLevel();
    }

    /**
     * @return MorphMany<Review, $this>
     */
    public function approvedReviews(): MorphMany
    {
        return $this->reviews()->topLevel()->approved();
    }

    public function averageRating(): ?float
    {
        return $this->reviewsManager()->averageFor($this);
    }

    public function reviewsCount(): int
    {
        return $this->reviews()->topLevel()->count();
    }

    public function approvedReviewsCount(): int
    {
        return $this->reviewsManager()->countFor($this);
    }

    /**
     * @return array<int, int>
     */
    public function ratingDistribution(): array
    {
        return $this->reviewsManager()->distributionFor($this);
    }

    public function ratingSummary(): RatingSummary
    {
        return $this->reviewsManager()->summaryFor($this);
    }

    public function addReview(Model $author): PendingReview
    {
        return $this->reviewsManager()->for($this)->by($author);
    }

    private function reviewsManager(): Reviews
    {
        return app(Reviews::class);
    }
}
