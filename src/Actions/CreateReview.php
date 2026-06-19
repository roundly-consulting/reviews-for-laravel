<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\Events\ReviewCreated;
use RoundlyConsulting\Reviews\Models\Review;

final class CreateReview
{
    public function execute(CreateReviewData $data): Review
    {
        $review = $this->newReview();

        $review->title = $data->title;
        $review->content = $data->content;
        $review->meta = $data->meta;

        $review->author()->associate($data->author);
        $review->reviewable()->associate($data->reviewable);

        $review->save();

        ReviewCreated::dispatch($review);

        return $review;
    }

    private function newReview(): Review
    {
        /** @var class-string<Review> $model */
        $model = config('reviews.model', Review::class);

        return new $model;
    }
}
