<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use RoundlyConsulting\Reviews\Events\ReviewVerified;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * Sets or clears a review's `verified` flag (a verified purchase / verified reviewer) and
 * dispatches {@see ReviewVerified} with the new state. Idempotent: a review already in the
 * requested state is returned untouched and no event fires.
 */
final readonly class MarkReviewVerified
{
    public function execute(Review $review, bool $verified = true): Review
    {
        if ($review->verified === $verified) {
            return $review;
        }

        $review->verified = $verified;
        $review->save();

        ReviewVerified::dispatch($review, $verified);

        return $review;
    }
}
