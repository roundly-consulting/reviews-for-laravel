<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewRejected;
use RoundlyConsulting\Reviews\Models\Review;

final class RejectReview
{
    public function execute(Review $review, ?string $reason = null): Review
    {
        if ($review->isRejected()) {
            return $review;
        }

        $review->status = ReviewStatus::Rejected;
        $review->approved_at = null;

        if ($reason !== null) {
            $meta = $review->meta ?? collect();
            $meta->put('rejection_reason', $reason);
            $review->meta = $meta;
        }

        $review->save();

        ReviewRejected::dispatch($review, $reason);

        return $review;
    }
}
