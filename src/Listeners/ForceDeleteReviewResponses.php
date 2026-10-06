<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Listeners;

use RoundlyConsulting\Reviews\Models\Review;

/**
 * Force-deletes a review's owner responses — live and soft-deleted — through Eloquent just before
 * the review itself goes, so each response runs its own hooks (its photos are purged, its own
 * responses follow it) instead of being promoted to a top-level review. Wired to the configured
 * model's `forceDeleting` event in the service provider rather than baked into the (swappable)
 * model.
 *
 * A query-builder delete fires no model event; the `parent_id` foreign key cascades those rows
 * instead (the `cascade_review_responses_on_delete` migration).
 */
final class ForceDeleteReviewResponses
{
    public function handle(Review $review): void
    {
        foreach ($review->responses()->withTrashed()->get() as $response) {
            $response->forceDelete();
        }
    }
}
