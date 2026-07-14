<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Traits\Macroable;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\MediaLibrary\Support\MediaModel;
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
use RoundlyConsulting\Reviews\Support\ReviewModel;
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
            photoCount: $this->photoCountFor($reviewable),
            reviewsWithPhotos: $this->reviewsWithPhotosFor($reviewable),
        );
    }

    /**
     * Total number of photos across a subject's approved, top-level reviews.
     */
    public function photoCountFor(Model $reviewable): int
    {
        return $this->photosQueryFor($reviewable)?->count() ?? 0;
    }

    /**
     * Number of a subject's approved, top-level reviews that carry at least one photo.
     */
    public function reviewsWithPhotosFor(Model $reviewable): int
    {
        return $this->photosQueryFor($reviewable)?->distinct()->count('model_id') ?? 0;
    }

    /**
     * A query over the media rows in the photos bucket owned by a subject's approved,
     * top-level reviews. Returns null when photos are disabled.
     *
     * @return Builder<Media>|null
     */
    protected function photosQueryFor(Model $reviewable): ?Builder
    {
        if (! (bool) config('reviews.photos.enabled', true)) {
            return null;
        }

        $review = ReviewModel::new();

        $bucket = config('reviews.photos.bucket', 'photos');
        $bucket = is_string($bucket) && $bucket !== '' ? $bucket : 'photos';

        $reviewIds = $this->approvedQueryFor($reviewable)->pluck($review->getKeyName());

        return MediaModel::query()
            ->where('bucket_name', $bucket)
            ->where('model_type', $review->getMorphClass())
            ->whereIn('model_id', $reviewIds);
    }

    /**
     * @return Builder<Review>
     */
    protected function approvedQueryFor(Model $reviewable): Builder
    {
        return ReviewModel::query()->topLevel()->approved()->for($reviewable);
    }
}
