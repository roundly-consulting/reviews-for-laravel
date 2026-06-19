<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Actions\ApproveReview;
use RoundlyConsulting\Reviews\Actions\DeleteReview;
use RoundlyConsulting\Reviews\Actions\RejectReview;
use RoundlyConsulting\Reviews\Actions\UpdateReview;
use RoundlyConsulting\Reviews\DataTransferObjects\RatingSummary;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\PendingReview;

final class Reviews
{
    public function __construct(
        private readonly ApproveReview $approveReview = new ApproveReview,
        private readonly RejectReview $rejectReview = new RejectReview,
        private readonly UpdateReview $updateReview = new UpdateReview,
        private readonly DeleteReview $deleteReview = new DeleteReview,
    ) {}

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
    private function approvedQueryFor(Model $reviewable): Builder
    {
        /** @var class-string<Review> $model */
        $model = config('reviews.model', Review::class);

        return $model::query()->approved()->for($reviewable);
    }
}
