<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Reviews\DataTransferObjects\RatingSummary;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Reviews as ReviewsManager;
use RoundlyConsulting\Reviews\Support\PendingReview;
use RoundlyConsulting\Reviews\Testing\ReviewsFake;

/**
 * @method static PendingReview for(Model $reviewable)
 * @method static Review approve(Review $review)
 * @method static Review reject(Review $review, ?string $reason = null)
 * @method static Review update(Review $review, UpdateReviewData $data)
 * @method static void delete(Review $review)
 * @method static Review respond(Review $review, Model $author, string $content, ?string $title = null)
 * @method static ReviewVote vote(Review $review, Model $voter, bool $helpful = true)
 * @method static void removeVote(Review $review, Model $voter)
 * @method static float|null averageFor(Model $reviewable)
 * @method static int countFor(Model $reviewable)
 * @method static array<int, int> distributionFor(Model $reviewable)
 * @method static RatingSummary summaryFor(Model $reviewable)
 * @method static ReviewsFake fake()
 *
 * @see ReviewsManager
 */
final class Reviews extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ReviewsManager::class;
    }
}
