<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Listeners;

use RoundlyConsulting\Reviews\Models\Review;

/**
 * Clears a review's photos bucket when the review is force-deleted (or pruned), so no orphaned
 * media rows or files linger. Soft-deletes are left untouched — a restored review keeps its
 * photos. Wired to the model's `forceDeleted` Eloquent event in the service provider rather than
 * baked into the (swappable) model.
 */
final class PurgeReviewPhotos
{
    public function handle(Review $review): void
    {
        if (! (bool) config('reviews.photos.enabled', true)) {
            return;
        }

        $review->clearMediaBucket($review->photosBucket());
    }
}
