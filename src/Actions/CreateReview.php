<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Events\ReviewCreated;
use RoundlyConsulting\Reviews\Events\ReviewRejected;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\PendingPhoto;
use RoundlyConsulting\Reviews\Support\ReviewModel;

final readonly class CreateReview
{
    public function __construct(
        private ValidatesRating $validateRating,
        private ModerateReview $moderate,
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

        // Associated before moderation, so a moderator can judge the author and the subject.
        $review->author()->associate($data->author);
        $review->reviewable()->associate($data->reviewable);

        $outcome = $this->moderate->execute(
            $review,
            approve: $data->approved || Config::boolean('reviews.auto_approve'),
        );

        // Persist the review and bind its photos atomically: an over-limit request or any failed
        // upload rolls the whole create back, so no review is left without its expected gallery.
        DB::transaction(function () use ($review, $data): void {
            $review->save();

            $this->attachPhotos($review, $data->photos);
        });

        // Dispatch after commit so listeners and broadcasts see the persisted photos. A review
        // that lands approved or rejected announces it like a later approve()/reject() would, so
        // its photos are warmed and "review went live" listeners run however it got there.
        ReviewCreated::dispatch($review);

        if ($review->isApproved()) {
            ReviewApproved::dispatch($review);
        } elseif ($review->isRejected()) {
            ReviewRejected::dispatch($review, $outcome->reason);
        }

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

    private function guardAgainstDuplicate(CreateReviewData $data): void
    {
        if (! (bool) config('reviews.one_per_author', false)) {
            return;
        }

        // Reviews only: an owner response the author wrote on this subject is not a review of it.
        $exists = $this->newReview()->newQuery()
            ->topLevel()
            ->authoredBy($data->author)
            ->for($data->reviewable)
            ->exists();

        if ($exists) {
            throw InvalidReviewException::duplicate();
        }
    }

    private function newReview(): Review
    {
        return ReviewModel::new();
    }
}
