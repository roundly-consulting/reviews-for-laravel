<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Events\ReviewVoted;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Support\ReviewVoteModel;

/**
 * Records (or flips) a single voter's helpful/unhelpful vote on a review.
 * One vote per voter per review: re-voting updates the existing row. The
 * review's denormalized helpful_count/unhelpful_count are recomputed after.
 */
final readonly class VoteOnReview
{
    public function __construct(
        private RecountReviewVotes $recount,
    ) {}

    public function execute(Review $review, Model $voter, bool $helpful = true): ReviewVote
    {
        /** @var ReviewVote $vote */
        $vote = ReviewVoteModel::query()->updateOrCreate(
            [
                'review_id' => $review->getKey(),
                'voter_type' => $voter->getMorphClass(),
                'voter_id' => $voter->getKey(),
            ],
            ['helpful' => $helpful],
        );

        $this->recount->execute($review);

        ReviewVoted::dispatch($review, $vote);

        return $vote;
    }
}
