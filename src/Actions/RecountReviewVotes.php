<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\ReviewVoteModel;

/**
 * Recomputes a review's denormalized helpful/unhelpful tallies from its votes.
 *
 * @internal building block of VoteOnReview / RemoveReviewVote.
 */
final readonly class RecountReviewVotes
{
    public function execute(Review $review): Review
    {
        $helpful = ReviewVoteModel::query()
            ->where('review_id', $review->getKey())
            ->where('helpful', true)
            ->count();

        $unhelpful = ReviewVoteModel::query()
            ->where('review_id', $review->getKey())
            ->where('helpful', false)
            ->count();

        $review->helpful_count = $helpful;
        $review->unhelpful_count = $unhelpful;
        $review->save();

        return $review;
    }
}
