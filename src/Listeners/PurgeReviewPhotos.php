<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Listeners;

use RoundlyConsulting\Reviews\Models\Review;

/**
 * Clears a review's photos bucket when the review is force-deleted (or pruned), so no orphaned
 * media rows or files linger. Soft-deletes are left untouched — a restored review keeps its
 * photos. Wired to the model's `forceDeleted` Eloquent event in the service provider rather than
 * baked into the (swappable) model.
 *
 * It runs whatever `reviews.photos.enabled` says: photos stored while the feature was on must not
 * outlive their review once it is switched off. Clearing works with the bucket undeclared.
 */
final class PurgeReviewPhotos
{
    public function handle(Review $review): void
    {
        $review->clearMediaBucket($review->photosBucket());
    }
}
