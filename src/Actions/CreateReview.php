<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewCreated;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\PendingPhoto;
use RoundlyConsulting\Reviews\Support\ReviewModel;

final readonly class CreateReview
{
    public function __construct(
        private ValidatesRating $validateRating,
    ) {}

    public function execute(CreateReviewData $data): Review
    {
        if ($data->rating === null && ($data->content === null || $data->content === '')) {
            throw InvalidReviewException::empty();
        }

        $this->validateRating->execute($data->rating);
        $this->guardAgainstDuplicate($data);

        $review = $this->newReview();

        $review->title = $data->title;
        $review->content = $data->content;
        $review->rating = $data->rating;
        $review->meta = $data->meta;
        $review->verified = $data->verified;

        $forceApproved = $data->approved || (bool) config('reviews.auto_approve', false);

        if ($forceApproved) {
            $review->status = ReviewStatus::Approved;
            $review->approved_at = CarbonImmutable::now();
        } else {
            $this->applyModeration($review);
        }

        $review->author()->associate($data->author);
        $review->reviewable()->associate($data->reviewable);

        // Persist the review and bind its photos atomically: an over-limit request or any failed
        // upload rolls the whole create back, so no review is left without its expected gallery.
        DB::transaction(function () use ($review, $data): void {
            $review->save();

            $this->attachPhotos($review, $data->photos);
        });

        // Dispatch after commit so listeners and broadcasts see the persisted photos.
        ReviewCreated::dispatch($review);

        return $review;
    }

    /**
     * @param  list<PendingPhoto>  $photos
     */
    private function attachPhotos(Review $review, array $photos): void
    {
        if ($photos === []) {
            return;
        }

        if (! (bool) config('reviews.photos.enabled', true)) {
            throw InvalidReviewException::photosDisabled();
        }

        $this->guardPhotoLimit($review, count($photos));

        $bucket = $review->photosBucket();

        foreach ($photos as $photo) {
            match ($photo->type) {
                PendingPhoto::TYPE_FILE => $review->addMedia($photo->value)->toMediaBucket($bucket),
                PendingPhoto::TYPE_URL => $review->addMediaFromUrl((string) $photo->value)->toMediaBucket($bucket),
                PendingPhoto::TYPE_DISK => $review->addMediaFromDisk((string) $photo->value, $photo->disk)->toMediaBucket($bucket),
                PendingPhoto::TYPE_DRAFT => $review->attachDraftMedia((string) $photo->value, $bucket),
                default => throw InvalidReviewException::photosDisabled(),
            };
        }
    }

    private function guardPhotoLimit(Review $review, int $incoming): void
    {
        $max = (int) config('reviews.photos.max', 5);

        if ($max <= 0) {
            return;
        }

        $existing = $review->photos()->count();

        if ($existing + $incoming > $max) {
            throw InvalidReviewException::tooManyPhotos($max);
        }
    }

    private function applyModeration(Review $review): void
    {
        $outcome = app(ReviewModerator::class)->moderate($review);

        if ($outcome->decision->isApprove()) {
            $review->status = ReviewStatus::Approved;
            $review->approved_at = CarbonImmutable::now();

            return;
        }

        if ($outcome->decision->isReject()) {
            $review->status = ReviewStatus::Rejected;

            if ($outcome->reason !== null) {
                $meta = $review->meta ?? collect();
                $meta->put('rejection_reason', $outcome->reason);
                $review->meta = $meta;
            }

            return;
        }

        $review->status = $this->defaultStatus();
    }

    private function guardAgainstDuplicate(CreateReviewData $data): void
    {
        if (! (bool) config('reviews.one_per_author', false)) {
            return;
        }

        $exists = $this->newReview()->newQuery()
            ->authoredBy($data->author)
            ->for($data->reviewable)
            ->exists();

        if ($exists) {
            throw InvalidReviewException::duplicate();
        }
    }

    private function defaultStatus(): ReviewStatus
    {
        $value = (string) config('reviews.default_status', ReviewStatus::Pending->value);

        return ReviewStatus::tryFrom($value) ?? ReviewStatus::Pending;
    }

    private function newReview(): Review
    {
        return ReviewModel::new();
    }
}
