<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;

/**
 * Recomputes a review's denormalized helpful/unhelpful tallies from its votes.
 */
final class RecountReviewVotes
{
    public function execute(Review $review): Review
    {
        /** @var class-string<ReviewVote> $model */
        $model = config('reviews.vote_model', ReviewVote::class);

        $helpful = $model::query()
            ->where('review_id', $review->getKey())
            ->where('helpful', true)
            ->count();

        $unhelpful = $model::query()
            ->where('review_id', $review->getKey())
            ->where('helpful', false)
            ->count();

        $review->helpful_count = $helpful;
        $review->unhelpful_count = $unhelpful;
        $review->save();

        return $review;
    }
}
