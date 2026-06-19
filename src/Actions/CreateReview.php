<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Carbon\CarbonImmutable;
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

        $approved = $data->approved || (bool) config('reviews.auto_approve', false);

        if ($approved) {
            $review->status = ReviewStatus::Approved;
            $review->approved_at = CarbonImmutable::now();
        } else {
            $review->status = $this->defaultStatus();
        }

        $review->author()->associate($data->author);
        $review->reviewable()->associate($data->reviewable);

        $review->save();

        ReviewCreated::dispatch($review);

        return $review;
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
