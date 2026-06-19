<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;

final class ReviewVoted
{
    use Dispatchable;

    public function __construct(
        public Review $review,
        public ReviewVote $vote,
    ) {}
}
