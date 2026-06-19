<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Reviews\Models\Review;

final class ReviewDeleted
{
    use Dispatchable;

    public function __construct(public Review $review) {}
}
