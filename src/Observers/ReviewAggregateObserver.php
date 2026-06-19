<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Observers;

use RoundlyConsulting\Reviews\Concerns\MaintainsReviewAggregates;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * Keeps a reviewable's denormalized aggregate columns in sync whenever a review
 * is created, updated, or deleted. Only acts on reviewables that opt in via the
 * MaintainsReviewAggregates trait, and only when config('reviews.cache_aggregates')
 * is enabled.
 */
final class ReviewAggregateObserver
{
    public function saved(Review $review): void
    {
        $this->sync($review);
    }

    public function deleted(Review $review): void
    {
        $this->sync($review);
    }

    public function restored(Review $review): void
    {
        $this->sync($review);
    }

    private function sync(Review $review): void
    {
        // Responses never affect ratings, so skip them entirely.
        if ($review->parent_id !== null) {
            return;
        }

        $reviewable = $review->reviewable;

        if ($reviewable === null) {
            return;
        }

        if (! in_array(MaintainsReviewAggregates::class, class_uses_recursive($reviewable), true)) {
            return;
        }

        if (method_exists($reviewable, 'recountReviews')) {
            $reviewable->recountReviews();
        }
    }
}
