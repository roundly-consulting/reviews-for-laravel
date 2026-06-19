<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Events\ReviewVoteRemoved;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;

/**
 * Removes a voter's vote from a review (idempotent) and recomputes the
 * denormalized tallies. Dispatches ReviewVoteRemoved only when a vote existed.
 */
final class RemoveReviewVote
{
    public function __construct(
        private readonly RecountReviewVotes $recount = new RecountReviewVotes,
    ) {}

    public function execute(Review $review, Model $voter): void
    {
        /** @var class-string<ReviewVote> $model */
        $model = config('reviews.vote_model', ReviewVote::class);

        $deleted = $model::query()
            ->where('review_id', $review->getKey())
            ->where('voter_type', $voter->getMorphClass())
            ->where('voter_id', $voter->getKey())
            ->delete();

        if ($deleted === 0) {
            return;
        }

        $this->recount->execute($review);

        ReviewVoteRemoved::dispatch($review, $voter);
    }
}
