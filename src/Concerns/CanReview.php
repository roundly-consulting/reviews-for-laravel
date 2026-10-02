<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\ReviewModel;

/**
 * Adds authoring capability to a model: the reviews it has written and lookups
 * for whether it has already reviewed a subject.
 *
 * @phpstan-require-extends Model
 */
trait CanReview
{
    /**
     * @return MorphMany<Review, $this>
     */
    public function reviewsAuthored(): MorphMany
    {
        return $this->morphMany(ReviewModel::class(), 'author');
    }

    /** Whether this model wrote a review of the subject — an owner response on it does not count. */
    public function hasReviewed(Model $reviewable): bool
    {
        return $this->reviewsAuthored()->topLevel()->for($reviewable)->exists();
    }

    /** This model's review of the subject (never one of its owner responses), or null. */
    public function reviewFor(Model $reviewable): ?Review
    {
        return $this->reviewsAuthored()->topLevel()->for($reviewable)->first();
    }
}
