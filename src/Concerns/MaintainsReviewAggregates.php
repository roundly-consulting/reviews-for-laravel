<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Concerns;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Reviews;

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
     * Recompute and persist the cached counters from the live (approved,
     * top-level) reviews. Saves quietly to avoid recursive observer loops.
     */
    public function recountReviews(): static
    {
        $manager = app(Reviews::class);

        $this->setAttribute($this->aggregateCountColumn(), $manager->countFor($this));
        $this->setAttribute($this->aggregateAvgColumn(), $manager->averageFor($this));

        $this->saveQuietly();

        return $this;
    }
}
