<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Reviews\Models\Review;

final class ReviewVoteRemoved
{
    use Dispatchable;

    public function __construct(
        public Review $review,
        public Model $voter,
    ) {}
}
