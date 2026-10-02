<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Support\RawExpression;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;

/**
 * Query scopes for the Review model. Lives in a trait so a swapped custom model
 * keeps them.
 *
 * @phpstan-require-extends Model
 */
trait HasReviewScopes
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', ReviewStatus::Approved->value);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ReviewStatus::Pending->value);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeRejected(Builder $query): void
    {
        $query->where('status', ReviewStatus::Rejected->value);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeRated(Builder $query): void
    {
        $query->whereNotNull('rating');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWithRatingOf(Builder $query, int $rating): void
    {
        $query->where('rating', $rating);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeMinRating(Builder $query, int $rating): void
    {
        $query->where('rating', '>=', $rating);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeAuthoredBy(Builder $query, Model $author): void
    {
        $query
            ->where('author_type', $author->getMorphClass())
            ->where('author_id', $author->getKey());
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeFor(Builder $query, Model $reviewable): void
    {
        $query
            ->where('reviewable_type', $reviewable->getMorphClass())
            ->where('reviewable_id', $reviewable->getKey());
    }

    /**
     * Most recently approved first, then never-approved (pending/rejected) reviews, each group
     * newest first.
     *
     * The NULL rank is explicit because engines disagree on where NULLs sort: pgsql puts them
     * first under DESC, sqlite and mysql last — so a mixed-status list would otherwise open with
     * the pending reviews on postgres only. A CASE (not `IS NULL` as a sort key) keeps it valid
     * on every grammar.
     *
     * @param  Builder<static>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $approvedAt = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn('approved_at'));

        $query->orderBy(new RawExpression("case when {$approvedAt} is null then 1 else 0 end"))
            ->orderByDesc($query->qualifyColumn('approved_at'))
            ->orderByDesc($query->qualifyColumn('created_at'));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeVerified(Builder $query): void
    {
        $query->where('verified', true);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeUnverified(Builder $query): void
    {
        $query->where('verified', false);
    }

    /**
     * Top-level reviews only (excludes owner responses). Used by aggregates so
     * responses never count toward ratings.
     *
     * @param  Builder<static>  $query
     */
    public function scopeTopLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeResponses(Builder $query): void
    {
        $query->whereNotNull('parent_id');
    }

    /**
     * Order by net helpful score (helpful minus unhelpful), most helpful first.
     *
     * Both tallies are UNSIGNED columns. MySQL and MariaDB raise ERROR 1690 ("BIGINT UNSIGNED
     * value is out of range") as soon as an unsigned subtraction goes negative — i.e. the first
     * time a review has more unhelpful than helpful votes — unless the server runs with
     * NO_UNSIGNED_SUBTRACTION, which Laravel's default sql_mode does not set. So on those engines
     * both operands are cast to SIGNED first; pgsql and sqlite have no unsigned integers and
     * subtract directly (neither accepts `AS SIGNED`).
     *
     * @param  Builder<static>  $query
     */
    public function scopeMostHelpful(Builder $query): void
    {
        $grammar = $query->getQuery()->getGrammar();
        $helpful = $grammar->wrap($query->qualifyColumn('helpful_count'));
        $unhelpful = $grammar->wrap($query->qualifyColumn('unhelpful_count'));

        $score = in_array($query->getModel()->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            ? "cast({$helpful} as signed) - cast({$unhelpful} as signed)"
            : "{$helpful} - {$unhelpful}";

        $query->orderBy(new RawExpression("({$score})"), 'desc')->orderByDesc($query->qualifyColumn('created_at'));
    }
}
