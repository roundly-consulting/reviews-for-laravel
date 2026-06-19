<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewUpdated;
use RoundlyConsulting\Reviews\Models\Review;

final class UpdateReview
{
    public function __construct(
        private readonly ValidatesRating $validateRating = new ValidatesRating,
    ) {}

    public function execute(Review $review, UpdateReviewData $data): Review
    {
        $contentChanged = false;

        if ($data->title !== null) {
            $review->title = $data->title;
        }

        if ($data->content !== null) {
            $review->content = $data->content;
            $contentChanged = true;
        }

        if ($data->rating !== null) {
            $this->validateRating->execute($data->rating);
            $review->rating = $data->rating;
            $contentChanged = true;
        }

        if ($data->meta !== null) {
            $review->meta = $data->meta;
        }

        if ($contentChanged && (bool) config('reviews.reset_status_on_edit', true)) {
            $review->status = ReviewStatus::Pending;
            $review->approved_at = null;
        }

        $review->save();

        ReviewUpdated::dispatch($review);

        return $review;
    }
}
