<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Events\ReviewVoteRemoved;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\ReviewVoteModel;

/**
 * Removes a voter's vote from a review (idempotent) and recomputes the denormalized
 * tallies, atomically under the review's row lock ({@see RecountReviewVotes}).
 * Dispatches ReviewVoteRemoved only when a vote existed.
 */
final readonly class RemoveReviewVote
{
    public function __construct(
        private RecountReviewVotes $recount,
    ) {}

    public function execute(Review $review, Model $voter): void
    {
        $deleted = $this->recount->execute($review, static fn (): int => (int) ReviewVoteModel::query()
            ->where('review_id', $review->getKey())
            ->where('voter_type', $voter->getMorphClass())
            ->where('voter_id', $voter->getKey())
            ->delete());

        if ($deleted === 0) {
            return;
        }

        ReviewVoteRemoved::dispatch($review, $voter);
    }
}
