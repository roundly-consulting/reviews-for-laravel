<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Testing;

use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\PendingReview;

/**
 * A PendingReview that records the created review against the fake while still
 * performing the real create (so DB reads/writes behave normally).
 */
final class RecordingPendingReview extends PendingReview
{
    private ReviewsFake $fake;

    public function __construct(ReviewsFake $fake)
    {
        parent::__construct();

        $this->fake = $fake;
    }

    public function create(): Review
    {
        $review = parent::create();

        $this->fake->recordCreated($review);

        return $review;
    }
}
