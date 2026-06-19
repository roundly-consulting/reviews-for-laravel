<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Carbon\CarbonImmutable;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Models\Review;

final class ApproveReview
{
    public function execute(Review $review): Review
    {
        if ($review->isApproved()) {
            return $review;
        }

        $review->status = ReviewStatus::Approved;
        $review->approved_at = CarbonImmutable::now();
        $review->save();

        ReviewApproved::dispatch($review);

        return $review;
    }
}
