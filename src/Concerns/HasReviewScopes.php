<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
     * @param  Builder<static>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('approved_at')->orderByDesc('created_at');
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
     * @param  Builder<static>  $query
     */
    public function scopeMostHelpful(Builder $query): void
    {
        $query->orderByRaw('(helpful_count - unhelpful_count) desc')->orderByDesc('created_at');
    }
}
