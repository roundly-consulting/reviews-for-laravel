<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Traits\Macroable;
use RoundlyConsulting\Reviews\Actions\ApproveReview;
use RoundlyConsulting\Reviews\Actions\DeleteReview;
use RoundlyConsulting\Reviews\Actions\RejectReview;
use RoundlyConsulting\Reviews\Actions\RemoveReviewVote;
use RoundlyConsulting\Reviews\Actions\RespondToReview;
use RoundlyConsulting\Reviews\Actions\UpdateReview;
use RoundlyConsulting\Reviews\Actions\VoteOnReview;
use RoundlyConsulting\Reviews\DataTransferObjects\RatingSummary;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Support\PendingReview;
use RoundlyConsulting\Reviews\Testing\ReviewsFake;

class Reviews
{
    use Macroable;

    public function __construct(
        protected readonly ApproveReview $approveReview = new ApproveReview,
        protected readonly RejectReview $rejectReview = new RejectReview,
        protected readonly UpdateReview $updateReview = new UpdateReview,
        protected readonly DeleteReview $deleteReview = new DeleteReview,
        protected readonly RespondToReview $respondToReview = new RespondToReview,
        protected readonly VoteOnReview $voteOnReview = new VoteOnReview,
        protected readonly RemoveReviewVote $removeReviewVote = new RemoveReviewVote,
    ) {}

    public static function fake(): ReviewsFake
    {
        $fake = new ReviewsFake;

        app()->instance(self::class, $fake);
        Facade::clearResolvedInstance(self::class);

        return $fake;
    }

    public function for(Model $reviewable): PendingReview
    {
        return (new PendingReview)->for($reviewable);
    }

    public function approve(Review $review): Review
    {
        return $this->approveReview->execute($review);
    }

    public function reject(Review $review, ?string $reason = null): Review
    {
        return $this->rejectReview->execute($review, $reason);
    }

    public function update(Review $review, UpdateReviewData $data): Review
    {
        return $this->updateReview->execute($review, $data);
    }

    public function delete(Review $review): void
    {
        $this->deleteReview->execute($review);
    }

    public function respond(Review $review, Model $author, string $content, ?string $title = null): Review
    {
        return $this->respondToReview->execute($review, $author, $content, $title);
    }

    public function vote(Review $review, Model $voter, bool $helpful = true): ReviewVote
    {
        return $this->voteOnReview->execute($review, $voter, $helpful);
    }

    public function removeVote(Review $review, Model $voter): void
    {
        $this->removeReviewVote->execute($review, $voter);
    }

    public function averageFor(Model $reviewable): ?float
    {
        $average = $this->approvedQueryFor($reviewable)->avg('rating');

        return $average === null ? null : (float) $average;
    }

    public function countFor(Model $reviewable): int
    {
        return $this->approvedQueryFor($reviewable)->count();
    }

    /**
     * @return array<int, int>
     */
    public function distributionFor(Model $reviewable): array
    {
        $rows = $this->approvedQueryFor($reviewable)
            ->whereNotNull('rating')
            ->selectRaw('rating, count(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        /** @var array<int, int> $distribution */
        $distribution = [];

        foreach ($rows as $rating => $count) {
            $distribution[(int) $rating] = (int) $count;
        }

        krsort($distribution);

        return $distribution;
    }

    public function summaryFor(Model $reviewable): RatingSummary
    {
        return new RatingSummary(
            average: $this->averageFor($reviewable),
            count: $this->countFor($reviewable),
            distribution: $this->distributionFor($reviewable),
        );
    }

    /**
     * @return Builder<Review>
     */
    protected function approvedQueryFor(Model $reviewable): Builder
    {
        /** @var class-string<Review> $model */
        $model = config('reviews.model', Review::class);

        return $model::query()->topLevel()->approved()->for($reviewable);
    }
}
