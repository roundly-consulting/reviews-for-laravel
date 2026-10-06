<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Closure;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\ReviewVoteModel;

/**
 * Applies a vote change to a review and recomputes its denormalized helpful/unhelpful tallies
 * from the votes table, as one atomic unit.
 *
 * One transaction takes the review's row lock **before** the change, so concurrent votes on a
 * review serialize: each recount sees every vote committed before it, and a slower recount can
 * never write back an older count over a newer one. (Locking first also keeps the vote insert's
 * foreign-key check from upgrading a shared lock into a deadlock.) The tallies are written with a
 * targeted update of those two columns only — the caller's other unsaved changes on the model
 * stay unsaved, and `updated_at` is left alone — then mirrored onto the model as clean values.
 *
 * @internal building block of VoteOnReview / RemoveReviewVote.
 */
final readonly class RecountReviewVotes
{
    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $change  The vote write, run under the review's row lock.
     * @return TResult
     */
    public function execute(Review $review, Closure $change): mixed
    {
        return $review->getConnection()->transaction(function () use ($review, $change): mixed {
            $review->newQueryWithoutScopes()
                ->whereKey($review->getKey())
                ->lockForUpdate()
                ->value($review->getKeyName());

            $result = $change();

            $this->recount($review);

            return $result;
        });
    }

    /**
     * The tallies are counted with **locking** reads: inside a host's enclosing REPEATABLE READ
     * transaction (MySQL's default) a plain count answers from the snapshot fixed before the review
     * lock was granted, and misses the vote that lock just waited for. PostgreSQL refuses a lock
     * clause beside an aggregate, so the rows are locked in a derived table and counted around it.
     */
    private function recount(Review $review): void
    {
        $votes = static function (bool $helpful) use ($review): int {
            $locked = ReviewVoteModel::query()
                ->where('review_id', $review->getKey())
                ->where('helpful', $helpful)
                ->select('id')
                ->sharedLock();

            return $locked->getQuery()->newQuery()->fromSub($locked, 'locked_votes')->count();
        };

        $tallies = [
            'helpful_count' => $votes(true),
            'unhelpful_count' => $votes(false),
        ];

        $review->newQueryWithoutScopes()->whereKey($review->getKey())->toBase()->update($tallies);

        $review->forceFill($tallies)->syncOriginalAttributes(array_keys($tallies));
    }
}
