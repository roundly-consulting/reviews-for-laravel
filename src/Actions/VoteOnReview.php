<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Events\ReviewVoted;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;

/**
 * Records (or flips) a single voter's helpful/unhelpful vote on a review.
 * One vote per voter per review: re-voting updates the existing row. The
 * review's denormalized helpful_count/unhelpful_count are recomputed after.
 */
final class VoteOnReview
{
    public function __construct(
        private readonly RecountReviewVotes $recount = new RecountReviewVotes,
    ) {}

    public function execute(Review $review, Model $voter, bool $helpful = true): ReviewVote
    {
        /** @var class-string<ReviewVote> $model */
        $model = config('reviews.vote_model', ReviewVote::class);

        /** @var ReviewVote $vote */
        $vote = $model::query()->updateOrCreate(
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
