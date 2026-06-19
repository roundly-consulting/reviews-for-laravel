<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewCreated;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;
use RoundlyConsulting\Reviews\Models\Review;

final class CreateReview
{
    public function __construct(
        private readonly ValidatesRating $validateRating = new ValidatesRating,
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

        $review->save();

        ReviewCreated::dispatch($review);

        return $review;
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
        /** @var class-string<Review> $model */
        $model = config('reviews.model', Review::class);

        return new $model;
    }
}
