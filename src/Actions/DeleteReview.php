<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use RoundlyConsulting\Reviews\Events\ReviewDeleted;
use RoundlyConsulting\Reviews\Models\Review;

final class DeleteReview
{
    public function execute(Review $review): void
    {
        $review->delete();

        ReviewDeleted::dispatch($review);
    }
}
