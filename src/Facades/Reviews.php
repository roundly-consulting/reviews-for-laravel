<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Facades;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\ReviewsManager;
use RoundlyConsulting\Reviews\Support\ReviewableScope;
use RoundlyConsulting\Reviews\Testing\ReviewsFake;

/**
 * @method static ReviewableScope for(Model $reviewable)
 * @method static Builder<Review> query()
 * @method static Review create(CreateReviewData $data)
 * @method static Review approve(Review $review)
 * @method static Review reject(Review $review, ?string $reason = null)
 * @method static Review update(Review $review, UpdateReviewData $data)
 * @method static void delete(Review $review)
 * @method static Review respond(Review $review, Model $author, string $content, ?string $title = null)
 * @method static ReviewVote vote(Review $review, Model $voter, bool $helpful = true)
 * @method static void removeVote(Review $review, Model $voter)
 * @method static Review verify(Review $review)
 * @method static Review unverify(Review $review)
 *
 * @see ReviewsManager
 */
final class Reviews extends Facade
{
    /**
     * Swap the manager for a recording fake. Operations still run against the database (so
     * reads, aggregates and events behave normally) while every mutation — through this facade,
     * an injected manager, a `for()->by()` builder, the `CanVoteOnReviews` / `HasReviews` traits
     * or a `Review` model method — is recorded for the `assert*()` methods.
     */
    public static function fake(): ReviewsFake
    {
        $fake = app(ReviewsFake::class);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return ReviewsManager::class;
    }
}
