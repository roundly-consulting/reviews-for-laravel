<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Observers;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Concerns\MaintainsReviewAggregates;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * Keeps a reviewable's denormalized aggregate columns in sync whenever a review
 * is created, updated, or deleted. Only acts on reviewables that opt in via the
 * MaintainsReviewAggregates trait, and only when config('reviews.cache_aggregates')
 * is enabled.
 *
 * Inside a transaction (a create always is) the subject's row is locked **before** the review
 * row is written, not only by the recount after it. Every writer of one subject then takes its
 * locks in the same order — subject, then reviews — so the recount's locking reads never wait on
 * another writer's review row while that writer waits on the subject (a deadlock on MySQL).
 */
final class ReviewAggregateObserver
{
    public function saving(Review $review): void
    {
        $this->lockSubject($review);
    }

    public function deleting(Review $review): void
    {
        $this->lockSubject($review);
    }

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

    private function lockSubject(Review $review): void
    {
        $reviewable = $this->maintainedSubject($review);

        // Outside a transaction the lock would be released before the write; the recount takes it.
        if ($reviewable === null || $reviewable->getConnection()->transactionLevel() === 0) {
            return;
        }

        $reviewable->newQueryWithoutScopes()
            ->whereKey($reviewable->getKey())
            ->lockForUpdate()
            ->value($reviewable->getKeyName());
    }

    private function sync(Review $review): void
    {
        $reviewable = $this->maintainedSubject($review);

        if ($reviewable !== null && method_exists($reviewable, 'recountReviews')) {
            $reviewable->recountReviews();
        }
    }

    /** The review's subject when it keeps cached aggregates; never for a response. */
    private function maintainedSubject(Review $review): ?Model
    {
        // Responses never affect ratings, so skip them entirely.
        if ($review->parent_id !== null) {
            return null;
        }

        $reviewable = $review->reviewable;

        if ($reviewable === null) {
            return null;
        }

        if (! in_array(MaintainsReviewAggregates::class, class_uses_recursive($reviewable), true)) {
            return null;
        }

        return $reviewable;
    }
}
