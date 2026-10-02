<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Models\Review;

final readonly class ApproveReview
{
    public function execute(Review $review): Review
    {
        if ($review->isApproved()) {
            return $review;
        }

        $review->status = ReviewStatus::Approved;
        $review->approved_at = CarbonImmutable::now();

        // A reason for a rejection that no longer stands would only mislead whoever reads it.
        if ($review->meta?->has('rejection_reason') === true) {
            $review->meta = $review->meta->except('rejection_reason');
        }

        $review->save();

        ReviewApproved::dispatch($review);

        return $review;
    }
}
