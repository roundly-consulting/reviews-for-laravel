<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Reviews\Models\Review;

final class ReviewResponded
{
    use Dispatchable;

    public function __construct(
        public Review $parent,
        public Review $response,
    ) {}
}
