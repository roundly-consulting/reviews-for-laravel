<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Reviews;
use RoundlyConsulting\Reviews\Support\ReviewVoteModel;

/**
 * Adds helpful-voting capability to a voter model.
 *
 * @phpstan-require-extends Model
 */
trait CanVoteOnReviews
{
    public function voteOn(Review $review, bool $helpful = true): ReviewVote
    {
        return app(Reviews::class)->vote($review, $this, $helpful);
    }

    public function removeVoteFrom(Review $review): void
    {
        app(Reviews::class)->removeVote($review, $this);
    }

    public function hasVotedOn(Review $review): bool
    {
        return $this->voteQueryFor($review)->exists();
    }

    public function votedHelpfulOn(Review $review): bool
    {
        return $this->voteQueryFor($review)->where('helpful', true)->exists();
    }

    /**
     * @return Builder<ReviewVote>
     */
    private function voteQueryFor(Review $review): Builder
    {
        return ReviewVoteModel::query()
            ->where('review_id', $review->getKey())
            ->where('voter_type', $this->getMorphClass())
            ->where('voter_id', $this->getKey());
    }
}
