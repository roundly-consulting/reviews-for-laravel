<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Support\ReviewModel;

/**
 * Opt-in denormalized aggregate caching for a reviewable model. When the host
 * model uses this trait (and ships the reviews_count / reviews_avg columns), the
 * package keeps those columns in sync on every approved-review change.
 *
 * Requires {@see HasReviews} for the relation/aggregate helpers and the host
 * table to carry the columns named by aggregateCountColumn()/aggregateAvgColumn().
 *
 * @phpstan-require-extends Model
 */
trait MaintainsReviewAggregates
{
    public function aggregateCountColumn(): string
    {
        return 'reviews_count';
    }

    public function aggregateAvgColumn(): string
    {
        return 'reviews_avg';
    }

    /**
     * Recompute and persist the cached counters from the live (approved, top-level) reviews.
     *
     * One transaction holds this model's row lock, so recounts of one subject serialize and a
     * slower one can never write back a count that misses a review committed meanwhile. The
     * reviews are counted with **locking** reads: under a REPEATABLE READ snapshot (MySQL's
     * default) a plain count would answer from before the lock was granted. PostgreSQL refuses a
     * lock clause beside an aggregate, so the rows are locked in a derived table and aggregated
     * around it. Only the two counter columns are written — no model events, no `updated_at`
     * bump, no other unsaved change on this model.
     */
    public function recountReviews(): static
    {
        $this->getConnection()->transaction(function (): void {
            $this->newQueryWithoutScopes()->whereKey($this->getKey())->lockForUpdate()->value($this->getKeyName());

            $approved = ReviewModel::query()->topLevel()->for($this)->approved()->select('rating')->sharedLock();

            $totals = (array) $approved->getQuery()->newQuery()
                ->fromSub($approved, 'approved_reviews')
                ->selectRaw('count(*) as review_count, avg(rating) as review_average')
                ->first();

            $average = $totals['review_average'] ?? null;

            $columns = [
                $this->aggregateCountColumn() => (int) ($totals['review_count'] ?? 0),
                $this->aggregateAvgColumn() => is_numeric($average) ? (float) $average : null,
            ];

            $this->newQueryWithoutScopes()->whereKey($this->getKey())->toBase()->update($columns);

            $this->forceFill($columns)->syncOriginalAttributes(array_keys($columns));
        });

        return $this;
    }
}
